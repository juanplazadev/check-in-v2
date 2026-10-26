/**
 * Prints a PDF without leaving the page: the PDF loads into a hidden same-origin frame and the
 * browser's print dialog opens on it. Where a frame cannot print (some mobile browsers), the PDF
 * opens in a new tab instead, whose viewer offers Print. `onLoaded` runs once the file has loaded.
 */
export function printPdf(url: string, onLoaded: () => void): void {
    const frame = document.createElement('iframe');
    frame.style.position = 'fixed';
    frame.style.width = '0';
    frame.style.height = '0';
    frame.style.border = '0';
    frame.src = url;

    frame.onload = () => {
        onLoaded();

        try {
            frame.contentWindow?.focus();
            frame.contentWindow?.print();
        } catch {
            window.open(url, '_blank', 'noopener');
        }

        // Removing the frame while the dialog is open cancels the print in some browsers.
        window.setTimeout(() => frame.remove(), 60_000);
    };

    document.body.appendChild(frame);
}
