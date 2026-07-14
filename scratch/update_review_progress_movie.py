from pathlib import Path

p = Path("REVIEWS/REVIEW_PROGRESS.md")
s = p.read_text(encoding="utf-8")

replacements = [
    ("**Completed:** 113 (82.5%)", "**Completed:** 114 (83.2%)"),
    ("**Remaining:** 24 (17.5%)", "**Remaining:** 23 (16.8%)"),
    ("| 6     | Supporting Components          | 26    | 9         | 17        | 34.6%   |", "| 6     | Supporting Components          | 26    | 10        | 16        | 38.5%   |"),
    ("**Next Up:** app/Models/Movie.php", "**Next Up:** app/Models/Order.php"),
    (
        "⏳ 114-137. Models, Traits, Resources (pending/reconciliation required)",
        """✅ **114. app/Models/Movie.php**
Review Date: 2026-07-14
Score: 5.4/10
Status: Request changes - race-prone slug generation, mass-assignable pricing/visibility fields, weak pricing/date invariants, unclear status modeling
Document: `REVIEWS/files/Movie_model_review.md`

⏳ 115-137. Models, Traits, Resources (pending/reconciliation required)""",
    ),
    ("**Total Issues:** 1100+ (from 113 files reviewed/verified)", "**Total Issues:** 1116+ (from 114 files reviewed/verified)"),
    ("**Current:** 113 files completed/verified (Day 1)", "**Current:** 114 files completed/verified (Day 1)"),
    ("**Actual Rate:** 113 files/day ✅ Exceeding target significantly", "**Actual Rate:** 114 files/day ✅ Exceeding target significantly"),
    ("- 24 files remaining", "- 23 files remaining"),
]

for old, new in replacements:
    if old not in s:
        raise SystemExit(f"Missing pattern: {old!r}")
    s = s.replace(old, new, 1)

p.write_text(s, encoding="utf-8")
print("Updated REVIEW_PROGRESS.md for Movie.php")
