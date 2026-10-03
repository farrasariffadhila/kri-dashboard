"""Synthetic data generator for the Enterprise IT Risk KRI Dashboard.

Writes CSV files for: departments, servers, audit_issues, incidents,
phishing_tests and server_compliance_snapshots. Output is deterministic
(fixed seed) and covers the 6 months up to and including --as-of.

Injected anomaly: the "Kartu Kredit" (credit card) department suffers a spike
in non-compliant servers, open High-risk audit findings and platform outages
over the last ~3 months, making it the highest-risk department in the
reporting month.

Usage:
    .venv/bin/python generate.py                    # as-of 2026-09-30, writes to ./output
    .venv/bin/python generate.py --as-of 2026-09-30 --seed 42 --out output
"""

from __future__ import annotations

import argparse
from dataclasses import dataclass
from datetime import date, timedelta
from pathlib import Path

import numpy as np
import pandas as pd
from faker import Faker

DEFAULT_AS_OF = date(2026, 9, 30)  # project reporting period: September 2026
HISTORY_MONTHS = 6
AUDIT_WINDOW_DAYS = 540  # 18 months of findings, so there is an aged backlog
SLA_DAYS = {"High": 30, "Medium": 60, "Low": 90}  # remediation deadline per risk level
RISK_MIX = {"High": 0.20, "Medium": 0.45, "Low": 0.35}
STALE_PROB = 0.04  # findings that are never remediated

# (OS name, population weight). Servers on an end-of-life OS are always non-compliant.
SUPPORTED_OS = [
    ("Red Hat Enterprise Linux 9.4", 0.16),
    ("Red Hat Enterprise Linux 8.10", 0.20),
    ("Windows Server 2022 Datacenter", 0.13),
    ("Windows Server 2019 Standard", 0.14),
    ("Windows Server 2016 Standard", 0.07),
    ("Ubuntu Server 22.04 LTS", 0.09),
    ("Oracle Linux 8.9", 0.07),
    ("SUSE Linux Enterprise Server 15 SP5", 0.05),
    ("IBM AIX 7.3", 0.05),
    ("IBM AIX 7.2", 0.04),
]
EOL_OS = [
    ("Red Hat Enterprise Linux 7.9", 0.45),  # maintenance support ended June 2024
    ("Windows Server 2012 R2 Standard", 0.30),  # extended support ended October 2023
    ("Ubuntu Server 20.04 LTS", 0.15),  # standard support ended May 2025
    ("CentOS Linux 7.9", 0.10),  # EOL June 2024
]

# Hostname: <department prefix>-<role>-<environment>-<sequence>, e.g. kk-app-prd-017
HOST_ROLES = [("app", 0.28), ("db", 0.14), ("web", 0.14), ("api", 0.12), ("batch", 0.08),
              ("mq", 0.06), ("file", 0.06), ("mon", 0.05), ("jump", 0.03), ("ldap", 0.04)]
HOST_ENVS = [("prd", 0.50), ("drc", 0.20), ("uat", 0.15), ("dev", 0.15)]  # drc = disaster recovery site

# IT audit finding titles per risk level. Kept in Indonesian on purpose:
# they are record values for an Indonesian bank, not code.
AUDIT_TITLES = {
    "High": [
        "Patch keamanan kritikal belum diterapkan lebih dari 30 hari",
        "Akses administrator server produksi tanpa MFA",
        "Akun karyawan yang sudah resign masih aktif di aplikasi inti",
        "Server produksi menjalankan sistem operasi end-of-life",
        "Backup database produksi tidak pernah diuji restore",
        "Password default pada perangkat jaringan belum diganti",
        "Audit log database produksi tidak diaktifkan",
        "Firewall rule mengizinkan akses any-any ke segmen produksi",
        "Kredensial aplikasi tersimpan plaintext di file konfigurasi",
        "Disaster Recovery Plan belum diuji dalam 12 bulan terakhir",
    ],
    "Medium": [
        "Review hak akses pengguna tidak dilakukan setiap triwulan",
        "Developer memiliki akses langsung ke lingkungan produksi",
        "Perubahan darurat tidak melalui prosedur change management",
        "Agen EDR pada sebagian endpoint tidak ter-update",
        "Kebijakan password belum mewajibkan minimal 12 karakter",
        "Sertifikat TLS aplikasi internal kedaluwarsa",
        "Monitoring kapasitas server tidak memiliki ambang peringatan",
        "Hasil vulnerability scan tidak ditindaklanjuti",
        "Sesi aplikasi yang idle tidak otomatis logout",
        "Inventaris aset TI tidak mutakhir",
    ],
    "Low": [
        "SOP operasional TI belum diperbarui sejak 2023",
        "Banner peringatan belum dipasang pada halaman login",
        "Jadwal pelatihan security awareness tidak terdokumentasi",
        "Penamaan server tidak sesuai standar konfigurasi",
        "Bukti review log tidak disetujui atasan",
        "Kontrak vendor belum mencantumkan klausul kerahasiaan data",
        "Label klasifikasi data belum diterapkan pada dokumen",
        "Log akses fisik ruang server tidak lengkap",
    ],
}
# PCI DSS v4.0 assessment findings used for the Kartu Kredit anomaly.
PCI_DSS_TITLES = [
    "PCI DSS 1.3: Segmentasi jaringan CDE tidak memadai",
    "PCI DSS 2.2: Hardening server switching kartu belum diterapkan",
    "PCI DSS 3.5: Data PAN tersimpan tanpa enkripsi di server aplikasi kartu",
    "PCI DSS 6.3.3: Patch kritikal pada server CDE melewati batas 1 bulan",
    "PCI DSS 7.2: Hak akses berlebih pada database pemegang kartu",
    "PCI DSS 8.4: Akses ke lingkungan CDE tanpa MFA",
    "PCI DSS 10.2: Log akses ke data pemegang kartu tidak lengkap",
    "PCI DSS 11.3: Pemindaian kerentanan internal triwulanan tidak dilakukan",
]
INTERNAL_AUDITORS = 12  # internal IT audit team
PCI_ASSESSORS = 3  # external QSAs for the PCI DSS assessment


@dataclass(frozen=True)
class DeptProfile:
    id: int
    name: str
    host_prefix: str  # server hostname prefix
    ip_octet: int  # server addresses are 10.<ip_octet>.<vlan>.<host>
    servers: int  # current inventory size
    nc_rate: float  # baseline share of non-compliant servers
    eol_share: float  # share of non-compliant servers running an EOL OS
    headcount: int
    phish_rate: float  # baseline phishing simulation click rate
    apps: int  # critical applications under availability monitoring
    outage_rate: float  # mean outages per application per month
    outage_median_min: float  # median duration of a single outage (minutes)
    audits: int  # findings raised within AUDIT_WINDOW_DAYS
    close_speed: float  # time to remediate as a fraction of SLA (lower = faster)


PROFILES = [
    DeptProfile(1, "Ultra Mikro", "um", 21, 1450, 0.085, 0.35, 2600, 0.12, 18, 1.2, 30, 150, 0.85),
    DeptProfile(2, "Digital Banking", "dgb", 32, 1180, 0.060, 0.25, 950, 0.05, 24, 1.5, 15, 130, 0.70),
    DeptProfile(3, "Treasury", "trs", 45, 420, 0.045, 0.30, 310, 0.06, 9, 0.6, 20, 70, 0.65),
    DeptProfile(4, "Kartu Kredit", "kk", 52, 760, 0.070, 0.35, 720, 0.08, 14, 1.0, 25, 110, 0.80),
    DeptProfile(5, "HRD", "hrd", 60, 210, 0.100, 0.40, 480, 0.16, 6, 1.6, 45, 55, 1.00),
]

# Kartu Kredit anomaly: a delayed card-platform migration breaks the July-September patch cycles,
# destabilises the card switching platform, and a PCI DSS assessment raises many High findings.
ANOMALY_DEPT = "Kartu Kredit"
ANOMALY_NC_OFFSET = [0.0, 0.0, 0.01, 0.05, 0.15, 0.28]  # added to nc_rate, oldest -> newest month
ANOMALY_EOL_SHARE = 0.55
ANOMALY_WINDOW_DAYS = 90
ANOMALY_CLOSE_SLOWDOWN = 1.8
ANOMALY_EXTRA_HIGH_AUDITS = 26
ANOMALY_OUTAGE_MULTIPLIER = [1.5, 2.5, 3.5]  # outage frequency multiplier for the last 3 months
ANOMALY_OUTAGE_MEDIAN_MIN = 45  # longer outages during the same 3 months
ANOMALY_PHISH_OFFSET = 0.02  # last 2 months


def parse_args() -> argparse.Namespace:
    p = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    p.add_argument("--as-of", type=date.fromisoformat, default=DEFAULT_AS_OF, help="reference date (YYYY-MM-DD)")
    p.add_argument("--seed", type=int, default=42)
    p.add_argument("--out", type=Path, default=Path(__file__).parent / "output")
    return p.parse_args()


def month_list(as_of: date) -> list[pd.Period]:
    current = pd.Period(as_of, freq="M")
    return [current - k for k in range(HISTORY_MONTHS - 1, -1, -1)]


def month_minutes(period: pd.Period, as_of: date) -> int:
    """Elapsed minutes in the month. The as-of month is counted month-to-date."""
    if period == pd.Period(as_of, freq="M"):
        return as_of.day * 1440
    return period.days_in_month * 1440


def month_end(period: pd.Period, as_of: date) -> date:
    return min(period.end_time.date(), as_of)


def pick(rng: np.random.Generator, catalog: list[tuple[str, float]], size: int) -> np.ndarray:
    names = [n for n, _ in catalog]
    weights = np.array([w for _, w in catalog])
    return rng.choice(names, size=size, p=weights / weights.sum())


# ---------------------------------------------------------------- servers & snapshots


def nc_rate_path(rng: np.random.Generator, prof: DeptProfile) -> list[float]:
    """Share of non-compliant servers per month (oldest -> newest)."""
    rates = prof.nc_rate + rng.normal(0, 0.008, HISTORY_MONTHS)
    if prof.name == ANOMALY_DEPT:
        rates = prof.nc_rate + rng.normal(0, 0.005, HISTORY_MONTHS) + np.array(ANOMALY_NC_OFFSET)
    return np.clip(rates, 0.01, 0.95).tolist()


def hostnames(rng: np.random.Generator, prefix: str, n: int) -> list[str]:
    """Unique hostnames per department, numbered per role/environment combination."""
    df = pd.DataFrame({"role": pick(rng, HOST_ROLES, n), "env": pick(rng, HOST_ENVS, n)})
    seq = df.groupby(["role", "env"]).cumcount() + 1
    return [f"{prefix}-{r}-{e}-{s:03d}" for r, e, s in zip(df["role"], df["env"], seq)]


def generate_servers(rng: np.random.Generator, desc_rng: np.random.Generator, prof: DeptProfile,
                     nc_rate: float) -> pd.DataFrame:
    n = prof.servers
    n_nc = round(n * nc_rate)
    eol_share = ANOMALY_EOL_SHARE if prof.name == ANOMALY_DEPT else prof.eol_share
    n_eol = round(n_nc * eol_share)

    os_versions = np.concatenate([
        pick(rng, EOL_OS, n_eol),  # non-compliant: EOL operating system
        pick(rng, SUPPORTED_OS, n_nc - n_eol),  # non-compliant: patches behind
        pick(rng, SUPPORTED_OS, n - n_nc),  # compliant
    ])
    compliant = np.concatenate([np.zeros(n_nc, dtype=int), np.ones(n - n_nc, dtype=int)])

    # Unique IP per department: 10.<octet>.<vlan 0-63>.<host 2-253>
    slots = rng.choice(64 * 252, size=n, replace=False)
    ips = [f"10.{prof.ip_octet}.{s // 252}.{s % 252 + 2}" for s in slots]

    order = rng.permutation(n)
    return pd.DataFrame({
        "department_id": prof.id,
        "hostname": hostnames(desc_rng, prof.host_prefix, n),
        "ip_address": np.array(ips)[order],
        "os_version": os_versions[order],
        "is_compliant": compliant[order],
    })


def generate_snapshots(rng: np.random.Generator, prof: DeptProfile, months: list[pd.Period],
                       rates: list[float], current: pd.DataFrame) -> pd.DataFrame:
    rows = []
    growth = rng.uniform(0.004, 0.012)  # monthly inventory growth
    for k, (period, rate) in enumerate(zip(months, rates)):
        back = len(months) - 1 - k
        if back == 0:  # reporting month mirrors the current servers table exactly
            total = len(current)
            nc = int((current["is_compliant"] == 0).sum())
        else:
            total = round(prof.servers * (1 - growth * back))
            nc = round(total * rate)
        rows.append({"department_id": prof.id, "month": str(period), "total_servers": total, "non_compliant": nc})
    return pd.DataFrame(rows)


# ---------------------------------------------------------------- audit issues


def resolve(rng: np.random.Generator, identified: date, sla: int, speed: float, as_of: date) -> date | None:
    """Closing date of a finding, or None if it is still open at as_of."""
    if rng.random() < STALE_PROB:
        return None
    days = max(1, round(sla * speed * rng.lognormal(0, 0.45)))
    closed = identified + timedelta(days=days)
    return closed if closed <= as_of else None


def auditor_pool(fake: Faker, size: int) -> list[str]:
    names: set[str] = set()
    while len(names) < size:
        names.add(f"{fake.first_name()} {fake.last_name()}")
    return sorted(names)


def generate_audits(rng: np.random.Generator, desc_rng: np.random.Generator, fake: Faker, prof: DeptProfile,
                    as_of: date, auditors: list[str], assessors: list[str]) -> pd.DataFrame:
    is_anomaly = prof.name == ANOMALY_DEPT
    anomaly_start = as_of - timedelta(days=ANOMALY_WINDOW_DAYS)
    rows = []

    risks = rng.choice(list(RISK_MIX), size=prof.audits, p=list(RISK_MIX.values()))
    for risk in risks:
        identified = fake.date_between(as_of - timedelta(days=AUDIT_WINDOW_DAYS), as_of)
        speed = prof.close_speed
        if is_anomaly and identified >= anomaly_start:
            speed *= ANOMALY_CLOSE_SLOWDOWN
        rows.append((desc_rng.choice(AUDIT_TITLES[risk]), desc_rng.choice(auditors), risk, identified,
                     resolve(rng, identified, SLA_DAYS[risk], speed, as_of)))

    if is_anomaly:
        # PCI DSS findings: concentrated in recent weeks, almost none closed yet.
        for _ in range(ANOMALY_EXTRA_HIGH_AUDITS):
            identified = as_of - timedelta(days=int(rng.triangular(0, 25, 95)))
            closed = None
            if rng.random() < 0.15:
                candidate = identified + timedelta(days=int(rng.integers(10, 30)))
                closed = candidate if candidate <= as_of else None
            rows.append((desc_rng.choice(PCI_DSS_TITLES), desc_rng.choice(assessors), "High", identified, closed))

    df = pd.DataFrame(rows, columns=["title", "auditor", "risk_level", "identified_date", "closed_date"])
    df["department_id"] = prof.id
    df["status"] = np.where(df["closed_date"].isna(), "Open", "Closed")
    df["due_date"] = [d + timedelta(days=SLA_DAYS[r]) for r, d in zip(df["risk_level"], df["identified_date"])]
    return df.sort_values("identified_date")


# ---------------------------------------------------------------- incidents & phishing


def generate_incidents(rng: np.random.Generator, prof: DeptProfile, months: list[pd.Period],
                       as_of: date) -> pd.DataFrame:
    """One row = monthly availability summary of one critical application in the department."""
    rows = []
    anomaly_from = len(months) - len(ANOMALY_OUTAGE_MULTIPLIER)
    for k, period in enumerate(months):
        minutes = month_minutes(period, as_of)
        lam = prof.outage_rate * minutes / (30.44 * 1440)
        median = prof.outage_median_min
        if prof.name == ANOMALY_DEPT and k >= anomaly_from:
            lam *= ANOMALY_OUTAGE_MULTIPLIER[k - anomaly_from]
            median = ANOMALY_OUTAGE_MEDIAN_MIN
        for outages in rng.poisson(lam, size=prof.apps):
            downtime = int(round(rng.lognormal(np.log(median), 0.9, size=outages).sum()))
            downtime = min(downtime, minutes)
            rows.append({
                "department_id": prof.id,
                "month": str(period),
                "total_downtime_minutes": downtime,
                "actual_uptime_pct": round(100 * (1 - downtime / minutes), 2),
            })
    return pd.DataFrame(rows)


def generate_phishing(rng: np.random.Generator, prof: DeptProfile, months: list[pd.Period]) -> pd.DataFrame:
    """One phishing simulation campaign per department per month."""
    rows = []
    for k, period in enumerate(months):
        tested = int(prof.headcount * rng.uniform(0.88, 0.97))
        rate = prof.phish_rate * (1 - 0.015 * k) + rng.normal(0, 0.006)  # awareness training effect
        if prof.name == ANOMALY_DEPT and k >= len(months) - 2:
            rate += ANOMALY_PHISH_OFFSET
        rows.append({
            "department_id": prof.id,
            "month": str(period),
            "total_employees_tested": tested,
            "total_failed": int(rng.binomial(tested, float(np.clip(rate, 0.005, 0.6)))),
        })
    return pd.DataFrame(rows)


# ---------------------------------------------------------------- validation


def with_ids(df: pd.DataFrame, columns: list[str]) -> pd.DataFrame:
    df = df.reset_index(drop=True)
    df.insert(0, "id", range(1, len(df) + 1))
    return df[["id", *columns]]


def validate(tables: dict[str, pd.DataFrame], months: list[pd.Period], as_of: date) -> None:
    servers, audits = tables["servers"], tables["audit_issues"]
    snaps = tables["server_compliance_snapshots"]
    current = str(months[-1])

    assert servers["ip_address"].is_unique, "duplicate server IP"
    assert servers["hostname"].is_unique, "duplicate server hostname"
    assert audits["title"].notna().all() and audits["auditor"].notna().all()
    assert ((audits["status"] == "Open") == audits["closed_date"].isna()).all(), "status does not match closed_date"
    assert (audits["due_date"] > audits["identified_date"]).all()
    assert (audits["closed_date"].dropna() <= as_of).all()
    assert (audits["identified_date"] <= as_of).all()

    cur_snap = snaps[snaps["month"] == current].set_index("department_id")
    by_dept = servers.groupby("department_id")["is_compliant"].agg(total="size", compliant="sum")
    assert (cur_snap["total_servers"] == by_dept["total"]).all()
    assert (cur_snap["non_compliant"] == by_dept["total"] - by_dept["compliant"]).all()

    names = tables["departments"].set_index("id")["name"]
    inc = tables["incidents"]
    ph = tables["phishing_tests"]
    ph_cur = ph[ph["month"] == current].set_index("department_id")
    kri = pd.DataFrame({
        "pct_unpatched": 100 * (1 - by_dept["compliant"] / by_dept["total"]),
        "open_high": audits[(audits["status"] == "Open") & (audits["risk_level"] == "High")]
        .groupby("department_id").size(),
        "uptime_pct": inc[inc["month"] == current].groupby("department_id")["actual_uptime_pct"].mean(),
        "phish_fail_pct": 100 * ph_cur["total_failed"] / ph_cur["total_employees_tested"],
    }).fillna(0)
    kri.index = kri.index.map(names)

    print(f"\nKRIs for reporting month {current} (data up to {as_of}):")
    print(kri.round(2).to_string())

    worst_server, worst_audit = kri["pct_unpatched"].idxmax(), kri["open_high"].idxmax()
    assert worst_server == ANOMALY_DEPT and worst_audit == ANOMALY_DEPT, (
        f"anomaly not visible: worst server KRI={worst_server}, worst audit KRI={worst_audit}")

    # Historical trail of the anomaly department, computed the same way as the historical-risk endpoint.
    dept_id = int(names[names == ANOMALY_DEPT].index[0])
    print(f"\n6-month trend for {ANOMALY_DEPT}:")
    a = audits[(audits["department_id"] == dept_id) & (audits["risk_level"] == "High")]
    for period in months:
        end = month_end(period, as_of)
        open_high = ((a["identified_date"] <= end) & (a["closed_date"].isna() | (a["closed_date"] > end))).sum()
        s = snaps[(snaps["department_id"] == dept_id) & (snaps["month"] == str(period))].iloc[0]
        m = inc[(inc["department_id"] == dept_id) & (inc["month"] == str(period))]
        print(f"  {period}: unpatched {100 * s.non_compliant / s.total_servers:5.1f}%  "
              f"open High audits {open_high:3d}  uptime {m['actual_uptime_pct'].mean():6.2f}%")


# ---------------------------------------------------------------- main


def main() -> None:
    args = parse_args()
    rng = np.random.default_rng(args.seed)
    Faker.seed(args.seed)
    fake = Faker("id_ID")
    months = month_list(args.as_of)

    # Descriptive columns (hostname, finding title, auditor) use their own random streams,
    # so adding or changing them never shifts the KRI numbers.
    desc_rng = np.random.default_rng(args.seed + 1)
    fake_people = Faker("id_ID")
    fake_people.seed_instance(args.seed + 1)
    auditors = auditor_pool(fake_people, INTERNAL_AUDITORS)
    assessors = auditor_pool(fake_people, PCI_ASSESSORS)

    parts: dict[str, list[pd.DataFrame]] = {k: [] for k in
                                            ("servers", "server_compliance_snapshots", "audit_issues",
                                             "incidents", "phishing_tests")}
    for prof in PROFILES:
        rates = nc_rate_path(rng, prof)
        servers = generate_servers(rng, desc_rng, prof, rates[-1])
        parts["servers"].append(servers)
        parts["server_compliance_snapshots"].append(generate_snapshots(rng, prof, months, rates, servers))
        parts["audit_issues"].append(generate_audits(rng, desc_rng, fake, prof, args.as_of, auditors, assessors))
        parts["incidents"].append(generate_incidents(rng, prof, months, args.as_of))
        parts["phishing_tests"].append(generate_phishing(rng, prof, months))

    tables = {
        "departments": pd.DataFrame([{"id": p.id, "name": p.name} for p in PROFILES]),
        "servers": with_ids(pd.concat(parts["servers"]),
                            ["department_id", "hostname", "ip_address", "os_version", "is_compliant"]),
        "audit_issues": with_ids(pd.concat(parts["audit_issues"]),
                                 ["department_id", "title", "auditor", "risk_level", "status", "identified_date",
                                  "due_date", "closed_date"]),
        "incidents": with_ids(pd.concat(parts["incidents"]),
                              ["department_id", "month", "total_downtime_minutes", "actual_uptime_pct"]),
        "phishing_tests": with_ids(pd.concat(parts["phishing_tests"]),
                                   ["department_id", "month", "total_employees_tested", "total_failed"]),
        "server_compliance_snapshots": with_ids(pd.concat(parts["server_compliance_snapshots"]),
                                                ["department_id", "month", "total_servers", "non_compliant"]),
    }

    validate(tables, months, args.as_of)

    args.out.mkdir(parents=True, exist_ok=True)
    print(f"\nCSV files written to {args.out}:")
    for name, df in tables.items():
        df.to_csv(args.out / f"{name}.csv", index=False)
        print(f"  {name}.csv  {len(df):>6} rows")


if __name__ == "__main__":
    main()
