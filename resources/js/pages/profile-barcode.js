export function generateBarcodeSvg(code, escapeHtml) {
    const width = 300;
    const height = 70;
    let position = 20;
    let random = String(code).split('').reduce((sum, character) => sum + character.charCodeAt(0), 0);
    let svg = `<svg width="100%" height="${height}" viewBox="0 0 ${width} ${height}" xmlns="http://www.w3.org/2000/svg"><rect width="${width}" height="${height}" fill="#ffffff"/>`;
    const nextSize = () => {
        random = (random * 9301 + 49297) % 233280;
        return (random % 3) + 1;
    };

    while (position < width - 20) {
        const barWidth = nextSize();
        svg += `<rect x="${position}" y="8" width="${barWidth}" height="40" fill="#000000"/>`;
        position += barWidth + nextSize();
    }

    svg += `<text x="50%" y="60" font-family="monospace" font-size="11" fill="#000000" text-anchor="middle" letter-spacing="2">${escapeHtml(code)}</text>`;
    return `${svg}</svg>`;
}
