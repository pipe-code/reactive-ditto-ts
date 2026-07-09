import React, { useState, useEffect } from 'react';
import ReactDOM from 'react-dom/client';
import { BrowserRouter, Route, Routes } from 'react-router-dom';
import axios from '@utils/axiosInstance';
import RouterProps from '@interface/routerProps';
import { RecoilRoot } from 'recoil';

// Layout
import Layout from '@ui/Layout/Layout';

// Containers
import Index from '@containers/Index/Index';
import Page from '@containers/Page/Page';
import Error404 from '@containers/Error404/Error404';

import '@styles/app.scss';

const App: React.FC = () => {

  // Use server-side pre-loaded router data when available (injected via wp_add_inline_script),
  // falling back to an API fetch so the app still works if the inline data is missing.
  const [routerMap, setRouterMap] = useState<RouterProps>(
    (window as any).__ROUTER_DATA__ || { basename: '/', items: [] }
  );

  useEffect(() => {
    if (routerMap.items.length > 0) return; // already have data from PHP inline script

    let isCancelled = false;
    const getRoutes = async () => axios.get('router/pages')
      .then(response => { if (response.data) setRouterMap(response.data) })
      .catch(err => { console.error(err) })

    if (!isCancelled) getRoutes();

    return () => { isCancelled = true; }
  }, [])

  return (
    <>
      {routerMap.items.length > 0 ?
        <BrowserRouter basename={routerMap.basename ?? ''}>
          <RecoilRoot>
            <Layout>
              <Routes>
                {routerMap.items.map(item => {
                  return item.post_type == "page" ?
                    <Route key={`${item.ID}-${item.lang}`} path={item.post_name} element={
                      <Page id={item.ID} postTitle={item.post_title} lang={item.lang} />
                    } />
                    :
                    <Route key={`${item.ID}-${item.lang}`} path={item.post_name} element={
                      <Index postTitle={item.post_title} />
                    } />
                })}
                {/* For single-page landing sites replace Error404 with <Navigate to="/" /> */}
                <Route path="*" element={<Error404 />} />
              </Routes>
            </Layout>
          </RecoilRoot>
        </BrowserRouter>
        : null}
    </>
  )
}

// The SPA owns scroll positioning (Page.tsx resets scroll on navigation and can
// scroll to anchors). Letting the browser restore scroll on back/forward leaves
// the page half-scrolled into the previous view when returning — take it over.
if ('scrollRestoration' in window.history) {
  window.history.scrollRestoration = 'manual';
}

const appElement = document.getElementById("app");

if (appElement) {
  // Remove the PHP-rendered instant loader before React takes over.
  // This runs synchronously so there is no visible gap between the PHP loader
  // disappearing and the React Loader component covering the screen.
  const pageLoader = document.getElementById('page-loader');
  if (pageLoader) pageLoader.remove();

  const root = ReactDOM.createRoot(appElement);
  root.render(
    <React.StrictMode>
      <App />
    </React.StrictMode>
  );
}
