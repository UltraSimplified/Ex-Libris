/**
 * Scale a photo down in the browser before it is uploaded.
 *
 * A camera's original can be 20MB and 45 megapixels, more than the server
 * accepts in one upload and more than it has the memory to decode. Drawn
 * onto a canvas no larger than the gallery keeps, it goes up as a JPEG of
 * a megabyte or two. The photo is turned upright as it is drawn, so the
 * copy needs no orientation tag. A photo already small enough, or one the
 * browser cannot draw, goes up as it is, and the server scales it instead.
 *
 * @param {File} file
 * @param {number} maxEdge
 * @returns {Promise<File>}
 */
export default async function scaleDown(file, maxEdge) {
    if (typeof window.createImageBitmap !== 'function') {
        return file;
    }

    let bitmap;

    try {
        bitmap = await window.createImageBitmap(file, {
            imageOrientation: 'from-image',
        });
    } catch {
        return file;
    }

    const scale = Math.min(1, maxEdge / Math.max(bitmap.width, bitmap.height));

    if (scale === 1 && file.size <= 8 * 1024 * 1024) {
        bitmap.close();

        return file;
    }

    const canvas = document.createElement('canvas');
    canvas.width = Math.round(bitmap.width * scale);
    canvas.height = Math.round(bitmap.height * scale);

    const context = canvas.getContext('2d');
    context.fillStyle = '#ffffff';
    context.fillRect(0, 0, canvas.width, canvas.height);
    context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
    bitmap.close();

    const blob = await new Promise((resolve) =>
        canvas.toBlob(resolve, 'image/jpeg', 0.92),
    );

    if (!blob) {
        return file;
    }

    return new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', {
        type: 'image/jpeg',
    });
}
