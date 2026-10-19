import { Component, type ReactNode } from 'react';
import ErrorPage from '@/pages/ErrorPage';

export default class ErrorBoundary extends Component<{ children: ReactNode }, { failed: boolean }> {
  state = { failed: false };

  static getDerivedStateFromError() {
    return { failed: true };
  }

  render() {
    return this.state.failed ? <ErrorPage unexpected /> : this.props.children;
  }
}
