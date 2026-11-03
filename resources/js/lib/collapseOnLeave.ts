/**
 * Transition hooks that close the gap a leaving row would otherwise leave for
 * the rows below to jump across. The height it collapses from has to be set in
 * pixels, as nothing animates from auto; the component's own CSS says how fast.
 */
export const collapseOnLeave = {
    beforeLeave(row: Element): void {
        const { style, offsetHeight } = row as HTMLElement;

        style.height = `${offsetHeight}px`;
        style.overflow = 'hidden';
    },
    leave(row: Element): void {
        const element = row as HTMLElement;

        // Read back so the browser has the starting height before it changes.
        void element.offsetHeight;
        element.style.height = '0';
    },
};
