from pathlib import Path

p = Path("REVIEWS/REVIEW_PROGRESS.md")
s = p.read_text(encoding="utf-8")

replacements = [
    ("**Completed:** 111 (81.0%)", "**Completed:** 112 (81.8%)"),
    ("**Remaining:** 26 (19.0%)", "**Remaining:** 25 (18.2%)"),
    ("| 6     | Supporting Components          | 26    | 7         | 19        | 26.9%   |", "| 6     | Supporting Components          | 26    | 8         | 18        | 30.8%   |"),
    ("**Next Up:** app/Models/Format.php", "**Next Up:** app/Models/LoginHistory.php"),
    (
        "⏳ 112-137. Models, Traits, Resources (pending/reconciliation required)",
        """✅ **112. app/Models/Format.php**
Review Date: 2026-07-14
Score: 6.6/10
Status: Request changes - pricing mass assignment, missing non-negative surcharge invariant, weak name uniqueness, no lifecycle policy for referenced formats
Document: `REVIEWS/files/Format_model_review.md`

⏳ 113-137. Models, Traits, Resources (pending/reconciliation required)""",
    ),
    ("**Total Issues:** 1080+ (from 111 files reviewed/verified)", "**Total Issues:** 1087+ (from 112 files reviewed/verified)"),
    ("**Current:** 111 files completed/verified (Day 1)", "**Current:** 112 files completed/verified (Day 1)"),
    ("**Actual Rate:** 111 files/day ✅ Exceeding target significantly", "**Actual Rate:** 112 files/day ✅ Exceeding target significantly"),
    ("- 26 files remaining", "- 25 files remaining"),
]

for old, new in replacements:
    if old not in s:
        raise SystemExit(f"Missing pattern: {old!r}")
    s = s.replace(old, new, 1)

p.write_text(s, encoding="utf-8")
print("Updated REVIEW_PROGRESS.md for Format.php")
