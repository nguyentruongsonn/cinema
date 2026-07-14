from pathlib import Path

p = Path("REVIEWS/REVIEW_PROGRESS.md")
s = p.read_text(encoding="utf-8")

replacements = [
    ("**Completed:** 108 (78.8%)  ", "**Completed:** 109 (79.6%)  "),
    ("**Remaining:** 29 (21.2%)", "**Remaining:** 28 (20.4%)"),
    ("| 6     | Supporting Components          | 26    | 4         | 22        | 15.4%   |", "| 6     | Supporting Components          | 26    | 5         | 21        | 19.2%   |"),
    ("**Next Up:** app/Models/Combo.php", "**Next Up:** app/Models/ComboItem.php"),
    (
        "⏳ 109-137. Models, Traits, Resources (pending/reconciliation required)",
        """✅ **109. app/Models/Combo.php**
Review Date: 2026-07-14
Score: 5.7/10
Status: Request changes - incorrect in-stock query, division-by-zero risk, non-atomic stock calculation, mass-assignable pricing/status
Document: `REVIEWS/files/Combo_model_review.md`

⏳ 110-137. Models, Traits, Resources (pending/reconciliation required)""",
    ),
    ("**Total Issues:** 1054+ (from 108 files reviewed/verified)", "**Total Issues:** 1064+ (from 109 files reviewed/verified)"),
    ("**Current:** 108 files completed/verified (Day 1)", "**Current:** 109 files completed/verified (Day 1)"),
    ("**Actual Rate:** 108 files/day ✅ Exceeding target significantly", "**Actual Rate:** 109 files/day ✅ Exceeding target significantly"),
    ("- 29 files remaining", "- 28 files remaining"),
]

for old, new in replacements:
    if old not in s:
        raise SystemExit(f"Missing pattern: {old!r}")
    s = s.replace(old, new, 1)

p.write_text(s, encoding="utf-8")
print("Updated REVIEW_PROGRESS.md for Combo.php")
