import { Component, type ReactNode } from 'react';

/** If one screen fails to draw, say so (and let the person go on) instead of leaving a blank page. Resets when the address changes. */
export default class ErrorBoundary extends Component<{ children: ReactNode; resetKey?: string }, { error: Error | null; key?: string }> {
  state: { error: Error | null; key?: string } = { error: null, key: this.props.resetKey };
  static getDerivedStateFromError(error: Error) { return { error }; }
  static getDerivedStateFromProps(props: { resetKey?: string }, state: { error: Error | null; key?: string }) {
    return props.resetKey !== state.key ? { error: null, key: props.resetKey } : null;
  }
  componentDidCatch(error: Error) { console.error(error); }
  render() {
    if (!this.state.error) return this.props.children;
    return (
      <div className="alert alert-danger my-4">
        <h2 className="h5">This screen could not be shown</h2>
        <p className="mb-2">Something unexpected went wrong while drawing it. Your data is safe. Try again, or open another page from the menu.</p>
        <p className="small text-muted mb-3"><code>{this.state.error.message}</code></p>
        <button className="btn btn-sm btn-outline-danger" onClick={() => window.location.reload()}>Reload the page</button>
      </div>
    );
  }
}
