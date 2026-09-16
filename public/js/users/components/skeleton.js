/**
 * Skeleton Loader Component
 * Generates skeleton loading placeholders for UI elements
 */

class SkeletonLoader {
  // Generate basic text skeleton
  static text(width = '100%', height = '1em') {
    const widthClass = width === '80%' ? 'skeleton-text--80' : width === '60%' ? 'skeleton-text--60' : 'skeleton-text--full';
    const heightClass = height === '1em' ? 'skeleton-text--base' : '';
    return `<div class="skeleton skeleton-text ${widthClass} ${heightClass}"></div>`;
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

  static mediaCard(count = 1) {
    return Array.from({ length: count }, () => `
      <article class="skeleton-variant skeleton-variant--media-card" aria-hidden="true">
        <div class="skeleton skeleton-variant__media"></div>
        <div class="skeleton skeleton-variant__title"></div>
        <div class="skeleton skeleton-variant__line"></div>
        <div class="skeleton skeleton-variant__line skeleton-variant__line--short"></div>
      </article>
    `).join('');
  }

  static horizontalList(count = 3) {
    return Array.from({ length: count }, () => `
      <div class="skeleton-variant skeleton-variant--horizontal" aria-hidden="true">
        <div class="skeleton skeleton-variant__thumb"></div>
        <div class="skeleton-variant__content">
          <div class="skeleton skeleton-variant__title"></div>
          <div class="skeleton skeleton-variant__line"></div>
        </div>
      </div>
    `).join('');
  }

  static summaryCard(count = 1) {
    return Array.from({ length: count }, () => `
      <div class="skeleton-variant skeleton-variant--summary" aria-hidden="true">
        <div class="skeleton skeleton-variant__label"></div>
        <div class="skeleton skeleton-variant__value"></div>
      </div>
    `).join('');
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
