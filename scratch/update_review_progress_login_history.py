from pathlib import Path

p = Path("REVIEWS/REVIEW_PROGRESS.md")
s = p.read_text(encoding="utf-8")

replacements = [
    ("**Completed:** 112 (81.8%)", "**Completed:** 113 (82.5%)"),
    ("**Remaining:** 25 (18.2%)", "**Remaining:** 24 (17.5%)"),
    ("| 6     | Supporting Components          | 26    | 8         | 18        | 30.8%   |", "| 6     | Supporting Components          | 26    | 9         | 17        | 34.6%   |"),
    ("**Next Up:** app/Models/LoginHistory.php", "**Next Up:** app/Models/Movie.php"),
    (
        "⏳ 113-137. Models, Traits, Resources (pending/reconciliation required)",
        """✅ **113. app/Models/LoginHistory.php**
Review Date: 2026-07-14
Score: 4.8/10
Status: Request changes - raw session token storage, mass-assignable audit fields, missing retention policy, timestamp semantics issues, user-agent parsing bugs
Document: `REVIEWS/files/LoginHistory_model_review.md`

⏳ 114-137. Models, Traits, Resources (pending/reconciliation required)""",
    ),
    ("**Total Issues:** 1087+ (from 112 files reviewed/verified)", "**Total Issues:** 1100+ (from 113 files reviewed/verified)"),
    ("**Current:** 112 files completed/verified (Day 1)", "**Current:** 113 files completed/verified (Day 1)"),
    ("**Actual Rate:** 112 files/day ✅ Exceeding target significantly", "**Actual Rate:** 113 files/day ✅ Exceeding target significantly"),
    ("- 25 files remaining", "- 24 files remaining"),
]

for old, new in replacements:
    if old not in s:
        raise SystemExit(f"Missing pattern: {old!r}")
    s = s.replace(old, new, 1)

p.write_text(s, encoding="utf-8")
print("Updated REVIEW_PROGRESS.md for LoginHistory.php")
