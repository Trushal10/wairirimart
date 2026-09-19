/**
 * Client-side image compression using the Canvas API — no dependencies.
 *
 * A raw DSLR/phone photo can be 5–15 MB. Uploading that to an admin panel over
 * a slow connection is what makes product/category forms feel "stuck for
 * minutes". This helper resizes + re-encodes the image before it ever leaves
 * the browser — a 5 MB JPEG typically becomes 200–500 KB with no perceptible
 * quality loss for product thumbnails.
 *
 * Usage:
 *   const smaller = await compressImage(file, { maxDimension: 1600, quality: 0.82 });
 *
 * @param {File} file  Original file from an <input type="file">.
 * @param {Object} [options]
 * @param {number} [options.maxDimension=1600] Max width OR height, whichever is larger.
 * @param {number} [options.quality=0.82]      JPEG/WebP quality, 0–1.
 * @param {string} [options.mimeType='image/jpeg'] Output MIME.
 * @param {number} [options.skipThresholdBytes=200000]
 *        If the original is already smaller than this AND already within
 *        maxDimension, return it unchanged (no re-encode cost).
 * @returns {Promise<File>} A File — same name, possibly different extension.
 */
export async function compressImage(file, options = {}) {
    const {
        maxDimension = 1600,
        quality = 0.82,
        mimeType = 'image/jpeg',
        skipThresholdBytes = 200_000,
    } = options;

    if (! (file instanceof File) && ! (file instanceof Blob)) {
        return file;
    }
    // Don't process non-image types (SVG, PDF, GIF-animated, etc.)
    if (! file.type || ! file.type.startsWith('image/')) {
        return file;
    }
    // Skip animated GIFs — canvas would collapse to first frame.
    if (file.type === 'image/gif') {
        return file;
    }

    const bitmap = await createBitmap(file);
    const { width, height } = bitmap;

    // Skip early: file is already small AND within bounds.
    if (
        file.size <= skipThresholdBytes
        && width <= maxDimension
        && height <= maxDimension
    ) {
        closeBitmap(bitmap);
        return file;
    }

    const scale = Math.min(1, maxDimension / Math.max(width, height));
    const targetWidth = Math.round(width * scale);
    const targetHeight = Math.round(height * scale);

    const canvas = document.createElement('canvas');
    canvas.width = targetWidth;
    canvas.height = targetHeight;
    const ctx = canvas.getContext('2d');

    // Prefer smoother down-sampling. Only relevant when we're actually scaling.
    ctx.imageSmoothingEnabled = true;
    ctx.imageSmoothingQuality = 'high';
    ctx.drawImage(bitmap, 0, 0, targetWidth, targetHeight);
    closeBitmap(bitmap);

    const blob = await canvasToBlob(canvas, mimeType, quality);
    if (! blob) return file;

    // If the "compressed" output is somehow bigger (rare — e.g. tiny PNG that
    // re-encodes larger as JPEG), fall back to the original.
    if (blob.size >= file.size) {
        return file;
    }

    const ext = mimeType === 'image/webp' ? 'webp' : 'jpg';
    const newName = renameToExt(file.name || 'image', ext);
    return new File([blob], newName, { type: mimeType, lastModified: Date.now() });
}

async function createBitmap(file) {
    if (typeof createImageBitmap === 'function') {
        try {
            // Faster path — decoded off the main thread on modern browsers.
            return await createImageBitmap(file);
        } catch (e) {
            // Fall through to <img> fallback (Safari + some private-mode contexts).
        }
    }
    return await new Promise((resolve, reject) => {
        const img = new Image();
        const url = URL.createObjectURL(file);
        img.onload = () => { URL.revokeObjectURL(url); resolve(img); };
        img.onerror = (e) => { URL.revokeObjectURL(url); reject(e); };
        img.src = url;
    });
}

function closeBitmap(bitmap) {
    if (bitmap && typeof bitmap.close === 'function') bitmap.close();
}

function canvasToBlob(canvas, mimeType, quality) {
    return new Promise((resolve) => {
        if (typeof canvas.toBlob === 'function') {
            canvas.toBlob(resolve, mimeType, quality);
        } else {
            // Extremely old fallback path.
            const dataUrl = canvas.toDataURL(mimeType, quality);
            const bytes = atob(dataUrl.split(',')[1]);
            const arr = new Uint8Array(bytes.length);
            for (let i = 0; i < bytes.length; i++) arr[i] = bytes.charCodeAt(i);
            resolve(new Blob([arr], { type: mimeType }));
        }
    });
}

function renameToExt(name, ext) {
    const dot = name.lastIndexOf('.');
    return (dot > 0 ? name.slice(0, dot) : name) + '.' + ext;
}

/**
 * Format bytes as human-friendly string.
 */
export function formatBytes(bytes) {
    if (! Number.isFinite(bytes) || bytes <= 0) return '0 KB';
    const units = ['B', 'KB', 'MB', 'GB'];
    let i = 0;
    while (bytes >= 1024 && i < units.length - 1) { bytes /= 1024; i++; }
    return bytes.toFixed(bytes < 10 && i > 0 ? 1 : 0) + ' ' + units[i];
}
