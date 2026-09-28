import { useEffect } from 'react';
import AOS from 'aos';
import 'aos/dist/aos.css';
import SEO from '../../components/common/SEO';
import OfferPopup from '../../components/common/OfferPopup';
import HeroBanner from './HeroBanner';
import FeaturedCollection from './FeaturedCollection';
import DynamicHomeSections from './DynamicHomeSections';
import FlashSale from './FlashSale';
import DealOfDay from './DealOfDay';
import Categories from './Categories';
import Brands from './Brands';
import CustomerReviews from './CustomerReviews';
import InstagramGallery from './InstagramGallery';
import Newsletter from './Newsletter';
import FAQSection from './FAQSection';

export default function Home() {
  useEffect(() => {
    AOS.init({ duration: 800, once: true, offset: 80 });
  }, []);

  return (
    <>
      <SEO description="Vibhaa Jewellery – All Your Jewellery, One Place." />
      <OfferPopup />
      <HeroBanner />
      <Categories />
      <FeaturedCollection />
      <DynamicHomeSections />
      <FlashSale />
      <DealOfDay />
      <Brands />
      <CustomerReviews />
      <InstagramGallery />
      <Newsletter />
      <FAQSection />
    </>
  );
}
