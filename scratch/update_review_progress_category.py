from pathlib import Path

p = Path("REVIEWS/REVIEW_PROGRESS.md")
s = p.read_text(encoding="utf-8")

replacements = [
    ("**Completed:** 110 (80.3%)", "**Completed:** 111 (81.0%)"),
    ("**Remaining:** 27 (19.7%)", "**Remaining:** 26 (19.0%)"),
    ("| 6     | Supporting Components          | 26    | 6         | 20        | 23.1%   |", "| 6     | Supporting Components          | 26    | 7         | 19        | 26.9%   |"),
    ("**Next Up:** app/Models/Contact.php", "**Next Up:** app/Models/Format.php"),
    (
        "⏳ 111-137. Models, Traits, Resources (pending/reconciliation required)",
        """✅ **111. app/Models/Category.php**
Review Date: 2026-07-14
Score: 6.4/10
Status: Request changes - weak slug invariants, mass-assignable visibility state, implicit pivot keys, missing active scope
Document: `REVIEWS/files/Category_model_review.md`

⏳ 112-137. Models, Traits, Resources (pending/reconciliation required)""",
    ),
    ("**Total Issues:** 1072+ (from 110 files reviewed/verified)", "**Total Issues:** 1080+ (from 111 files reviewed/verified)"),
    ("**Current:** 110 files completed/verified (Day 1)", "**Current:** 111 files completed/verified (Day 1)"),
    ("**Actual Rate:** 110 files/day ✅ Exceeding target significantly", "**Actual Rate:** 111 files/day ✅ Exceeding target significantly"),
    ("- 27 files remaining", "- 26 files remaining"),
]

for old, new in replacements:
    if old not in s:
        raise SystemExit(f"Missing pattern: {old!r}")
    s = s.replace(old, new, 1)

p.write_text(s, encoding="utf-8")
print("Updated REVIEW_PROGRESS.md for Category.php")
