export function createRoot(container: Element): { render: (node: unknown) => void } {
  return window.wp.element.createRoot(container);
}
