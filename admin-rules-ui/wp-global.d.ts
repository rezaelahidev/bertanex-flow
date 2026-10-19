export {};

declare global {
  interface Window {
    wp: {
      element: {
        createRoot: (container: Element) => { render: (node: unknown) => void };
        useState: unknown;
        useEffect: unknown;
        useLayoutEffect: unknown;
        useMemo: unknown;
        useCallback: unknown;
        useRef: unknown;
        useId: unknown;
        createElement: unknown;
        Fragment: unknown;
        StrictMode: unknown;
        forwardRef: unknown;
        createContext: unknown;
        useContext: unknown;
      };
      components: Record<string, React.ComponentType<Record<string, unknown>>>;
    };
  }
}
