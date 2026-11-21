export function printHtml(html) {
    return new Promise((resolve, reject) => {
        const frame = document.createElement('iframe');

        frame.setAttribute('aria-hidden', 'true');
        frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden';

        let removed = false;

        const remove = () => {
            if (!removed) {
                removed = true;
                frame.remove();
            }
        };

        frame.onload = () => {
            try {
                const win = frame.contentWindow;

                win.addEventListener('afterprint', remove);
                win.focus();
                win.print();
                setTimeout(remove, 60000);
                resolve();
            } catch (error) {
                remove();
                reject(error);
            }
        };

        frame.srcdoc = html;
        document.body.appendChild(frame);
    });
}
