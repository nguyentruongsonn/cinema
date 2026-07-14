from pathlib import Path

p = Path("REVIEWS/REVIEW_PROGRESS.md")
s = p.read_text(encoding="utf-8")

replacements = [
    ("**Last Updated:** 2026-07-14 07:20 PM  ", "**Last Updated:** 2026-07-14 07:30 PM  "),
    ("**Completed:** 105 (76.6%)  ", "**Completed:** 108 (78.8%)  "),
    ("**Remaining:** 32 (23.4%)", "**Remaining:** 29 (21.2%)"),
    ("| 6     | Supporting Components          | 26    | 1         | 25        | 3.8%    |", "| 6     | Supporting Components          | 26    | 4         | 22        | 15.4%   |"),
    ("**Next Up:** Phase 6 file discovery / next model, trait, or resource", "**Next Up:** app/Models/Combo.php"),
    (
        "⏳ 106-137. Models, Traits, Resources (pending/reconciliation required)",
        """✅ **106. app/Models/AuditLog.php**
Review Date: 2026-07-14
Score: 5.8/10
Status: Request changes - mass-assignable audit evidence, no value redaction, no immutability enforcement, polymorphic type coupling
Document: `REVIEWS/files/AuditLog_model_review.md`

✅ **107. app/Models/Banner.php**
Review Date: 2026-07-14
Score: 6.0/10
Status: Request changes - mass-assignable click analytics, unsafe URL persistence, free-form positions, missing date invariants
Document: `REVIEWS/files/Banner_model_review.md`

✅ **108. app/Models/Branch.php**
Review Date: 2026-07-14
Score: 6.4/10
Status: Approve with comments - mass-assignable operational state, missing relationships/deletion guards, untyped active scope
Document: `REVIEWS/files/Branch_model_review.md`

⏳ 109-137. Models, Traits, Resources (pending/reconciliation required)""",
    ),
    ("**Total Issues:** 1030+ (from 105 files reviewed/verified)", "**Total Issues:** 1054+ (from 108 files reviewed/verified)"),
    ("**Current:** 105 files completed/verified (Day 1)  ", "**Current:** 108 files completed/verified (Day 1)  "),
    ("**Actual Rate:** 105 files/day ✅ Exceeding target significantly  ", "**Actual Rate:** 108 files/day ✅ Exceeding target significantly  "),
    ("- 32 files remaining", "- 29 files remaining"),
    ("_Last updated: 2026-07-14 07:20 PM_  ", "_Last updated: 2026-07-14 07:30 PM_  "),
]

for old, new in replacements:
    if old not in s:
        raise SystemExit(f"Missing pattern: {old!r}")
    s = s.replace(old, new, 1)

p.write_text(s, encoding="utf-8")
print("Updated REVIEWS/REVIEW_PROGRESS.md")
