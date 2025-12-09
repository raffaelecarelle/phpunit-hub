import { onMounted } from 'vue';

export function useResizer(resizerId, sidebarId) {
    onMounted(() => {
        const resizer = document.getElementById(resizerId);
        const sidebar = document.getElementById(sidebarId);

        if (!resizer || !sidebar) return;

        let isResizing = false;

        const startResizing = (e) => {
            e.preventDefault();
            isResizing = true;
            document.body.style.cursor = 'col-resize';
            document.body.style.userSelect = 'none';
            document.addEventListener('mousemove', resize);
            document.addEventListener('mouseup', stopResizing);
        };

        const resize = (e) => {
            if (isResizing) {
                const sidebarWidth = e.clientX;
                if (sidebarWidth > 200 && sidebarWidth < window.innerWidth - 200) {
                    sidebar.style.width = `${sidebarWidth}px`;
                }
            }
        };

        const stopResizing = () => {
            isResizing = false;
            document.body.style.cursor = '';
            document.body.style.userSelect = '';
            document.removeEventListener('mousemove', resize);
            document.removeEventListener('mouseup', stopResizing);
        };

        resizer.addEventListener('mousedown', startResizing);
    });
}
