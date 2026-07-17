import { useEffect } from 'react';
import { useLocation } from 'react-router-dom';
import Header from '@components/Header/Header';
import Footer from '@components/Footer/Footer';
import Loader from '@ui/Loader/Loader';
import { useSetRecoilState } from 'recoil';
import { siteLoadedAtom } from '@utils/recoilStates';
import { pushVirtualPageview } from '@utils/tracking';

const Layout: React.FC<{children: React.ReactNode}> = ({ children }) => {

    const setAssetsLoaded = useSetRecoilState(siteLoadedAtom);
    const location = useLocation();

    // React Router never triggers a real browser page load, so analytics never
    // see a route change on their own — push the missing signal on every one.
    // See src/utils/tracking.tsx for why this matters.
    useEffect(() => {
        pushVirtualPageview(location.pathname);
    }, [location.pathname]);

    useEffect(() => {
        const handleLoad = () => { setAssetsLoaded(prevState => ({ ...prevState, assets: true })) };
    
        if (document.readyState === 'complete') handleLoad() 
        else {
            window.addEventListener('load', handleLoad);
            
            document.addEventListener('readystatechange', () => {
                if (document.readyState === 'complete') handleLoad();
            });
        }
    
        return () => {
            window.removeEventListener('load', handleLoad);
            document.removeEventListener('readystatechange', handleLoad);
        };
    }, [ setAssetsLoaded ]);

    return (
        <>
            <Header />
            <main>
                {children}
            </main>
            <Footer />
            <Loader />
        </>
    )
}

export default Layout;