import { Outlet, useLocation } from 'react-router-dom';
import AnnouncementBar from './AnnouncementBar';
import Navbar from './Navbar';
import BottomNav from './BottomNav';
import Footer from './Footer';
import MobileMenu from './MobileMenu';
import SearchOverlay from './SearchOverlay';
import WhatsAppFloat from './WhatsAppFloat';
import TrustBadges from '../common/TrustBadges';
import HomeStats from '../../pages/Home/HomeStats';

export default function Layout() {
  const { pathname } = useLocation();
  const isHome = pathname === '/';

  return (
    <div className="yulo-app-shell">
      <AnnouncementBar />
      <Navbar />
      <MobileMenu />
      <SearchOverlay />
      <main>
        <Outlet />
      </main>
      <TrustBadges />
      {isHome && <HomeStats />}
      <Footer />
      <WhatsAppFloat />
      <BottomNav />
    </div>
  );
}
