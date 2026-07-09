import { useEffect } from 'react';
import { useSetRecoilState } from 'recoil';
import { siteLoadedAtom } from '@utils/recoilStates';
import styles from './Footer.module.scss';

const Footer = () => {

    const setAssetsLoaded = useSetRecoilState(siteLoadedAtom);

    useEffect(() => {
        setAssetsLoaded(prevState => ({ ...prevState, footer: true }))
    }, [])

    return (
        <footer className={styles.Container}>
            © Reactive Ditto Theme {new Date().getFullYear()}
        </footer>
    )
}

export default Footer;