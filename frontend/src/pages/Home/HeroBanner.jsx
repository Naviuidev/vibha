import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Swiper, SwiperSlide } from 'swiper/react';
import { Pagination, EffectFade, Autoplay } from 'swiper/modules';
import { getStoredJson, resolveMediaUrl, setStoredJson } from '../../utils/helpers';
import api from '../../services/api';
import 'swiper/css';
import 'swiper/css/pagination';
import 'swiper/css/effect-fade';

const BANNER_CACHE_KEY = 'vibhaa.homeBanners';

function mapBanners(rows) {
  return (Array.isArray(rows) ? rows : [])
    .map((b) => ({
      src: resolveMediaUrl(b.image),
      link: b.link || '/shop',
      title: b.title || '',
    }))
    .filter((b) => b.src);
}

function readCachedBanners() {
  const cached = getStoredJson(BANNER_CACHE_KEY, []);
  return Array.isArray(cached) ? cached.filter((s) => s?.src) : [];
}

function SlideLink({ link, children }) {
  if (!link) return children;
  const internal = link.startsWith('/') && !link.startsWith('//');
  const className = 'hero-banner__slide-link';
  if (internal) {
    return (
      <Link to={link} className={className}>
        {children}
      </Link>
    );
  }
  return (
    <a href={link} className={className}>
      {children}
    </a>
  );
}

export default function HeroBanner() {
  const [slides, setSlides] = useState(readCachedBanners);

  useEffect(() => {
    let cancelled = false;

    (async () => {
      try {
        const { data } = await api.get('/banners', { params: { position: 'home', limit: 3 } });
        const rows = mapBanners(data?.data ?? []);
        if (cancelled) return;
        setSlides(rows);
        if (rows.length) setStoredJson(BANNER_CACHE_KEY, rows);
      } catch {
        // Keep cached banners if we already have them
      }
    })();

    return () => {
      cancelled = true;
    };
  }, []);

  const multi = slides.length > 1;

  return (
    <section className="hero-banner">
      {slides.length ? (
        <Swiper
          key={slides.map((s) => s.src).join('|')}
          className="hero-banner__swiper"
          modules={[Pagination, EffectFade, Autoplay]}
          effect="fade"
          fadeEffect={{ crossFade: true }}
          speed={900}
          loop={multi}
          autoplay={multi ? { delay: 5000, disableOnInteraction: false } : false}
          pagination={multi ? { clickable: true } : false}
          allowTouchMove={multi}
        >
          {slides.map((slide, i) => (
            <SwiperSlide key={`${slide.src}-${i}`}>
              <SlideLink link={slide.link}>
                <img src={slide.src} alt={slide.title || 'Hero banner'} />
              </SlideLink>
            </SwiperSlide>
          ))}
        </Swiper>
      ) : (
        <div className="hero-banner__placeholder" aria-hidden="true" />
      )}
    </section>
  );
}
