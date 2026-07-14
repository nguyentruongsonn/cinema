from pathlib import Path

p = Path("REVIEWS/REVIEW_PROGRESS.md")
s = p.read_text(encoding="utf-8")

replacements = [
    ("**Completed:** 109 (79.6%)", "**Completed:** 110 (80.3%)"),
    ("**Remaining:** 28 (20.4%)", "**Remaining:** 27 (19.7%)"),
    ("| 6     | Supporting Components          | 26    | 5         | 21        | 19.2%   |", "| 6     | Supporting Components          | 26    | 6         | 20        | 23.1%   |"),
    ("**Next Up:** app/Models/ComboItem.php", "**Next Up:** app/Models/Contact.php"),
    (
        "⏳ 110-137. Models, Traits, Resources (pending/reconciliation required)",
        """✅ **110. app/Models/ComboItem.php**
Review Date: 2026-07-14
Score: 6.1/10
Status: Request changes - missing quantity invariant, mass-assignable ownership keys, no duplicate combo-product guard, weak historical composition integrity
Document: `REVIEWS/files/ComboItem_model_review.md`

⏳ 111-137. Models, Traits, Resources (pending/reconciliation required)""",
    ),
    ("**Total Issues:** 1064+ (from 109 files reviewed/verified)", "**Total Issues:** 1072+ (from 110 files reviewed/verified)"),
    ("**Current:** 109 files completed/verified (Day 1)", "**Current:** 110 files completed/verified (Day 1)"),
    ("**Actual Rate:** 109 files/day ✅ Exceeding target significantly", "**Actual Rate:** 110 files/day ✅ Exceeding target significantly"),
    ("- 28 files remaining", "- 27 files remaining"),
]

for old, new in replacements:
    if old not in s:
        raise SystemExit(f"Missing pattern: {old!r}")
    s = s.replace(old, new, 1)

p.write_text(s, encoding="utf-8")
print("Updated REVIEW_PROGRESS.md for ComboItem.php")
