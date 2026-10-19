import React from 'react';
import ReactDOM from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import App from './App';
import ErrorBoundary from './components/ErrorBoundary';
import { ThemeProvider, applyInitialTheme } from './components/ThemeProvider';
import './index.css';

applyInitialTheme();
ReactDOM.createRoot(document.getElementById('root')!).render(
  <React.StrictMode>
    <BrowserRouter>
      <ThemeProvider><ErrorBoundary><App /></ErrorBoundary></ThemeProvider>
    </BrowserRouter>
  </React.StrictMode>,
);
