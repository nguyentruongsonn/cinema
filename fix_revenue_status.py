import sys
import re

php_path = 'app/Services/RevenueService.php'
with open(php_path, 'r', encoding='utf-8') as f:
    content = f.read()

# Replace STATUS_CONFIRMED = 'confirmed' with STATUS_CONFIRMED = 2
content = re.sub(r"private const STATUS_CONFIRMED = 'confirmed';", "private const STATUS_CONFIRMED = 2;", content)

with open(php_path, 'w', encoding='utf-8') as f:
    f.write(content)

print("Fixed STATUS_CONFIRMED in RevenueService.php")
