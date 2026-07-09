/**
 * Skeleton Loader Component
 * Generates skeleton loading placeholders for UI elements
 */

class SkeletonLoader {
  // Generate basic text skeleton
  static text(width = '100%', height = '1em') {
    return `<div class="skeleton skeleton-text" style="width: ${width}; height: ${height};"></div>`;
  }

  // Generate title skeleton
  static title() {
    return '<div class="skeleton skeleton-title"></div>';
  }

  // Generate subtitle skeleton
  static subtitle() {
    return '<div class="skeleton skeleton-subtitle"></div>';
  }

  // Generate image skeleton
  static image(aspectRatio = 'poster') {
    const cssClass = aspectRatio === 'square' ? 'skeleton-image-square' :
                     aspectRatio === 'poster' ? 'skeleton-image-poster' : '';
    return `<div class="skeleton skeleton-image ${cssClass}"></div>`;
  }

  // Generate movie card skeleton
  static movieCard() {
    return `
      <div class="skeleton-movie-card">
        ${this.image('poster')}
        ${this.title()}
        ${this.text('80%')}
        ${this.text('60%')}
      </div>
    `;
  }

  // Show skeleton in container
  static show(container, generator, count = 3) {
    const el = typeof container === 'string' ? document.querySelector(container) : container;
    if (!el) return;
    el.classList.add('loading');
    el.innerHTML = Array(count).fill(null).map(() => generator.call(this)).join('');
  }

  // Hide skeleton
  static hide(container, content = '') {
    const el = typeof container === 'string' ? document.querySelector(container) : container;
    if (!el) return;
    el.classList.remove('loading');
    if (content) el.innerHTML = content;
  }
}

if (typeof window !== 'undefined') {
  window.SkeletonLoader = SkeletonLoader;
}
