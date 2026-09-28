import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Swiper, SwiperSlide } from 'swiper/react';
import { Navigation, Autoplay } from 'swiper/modules';
import ProductCard from '../../components/ui/ProductCard';
import { productService } from '../../services/productService';
import { homeSectionService } from '../../services/homeSectionService';
import 'swiper/css';
import 'swiper/css/navigation';

const SPECIAL_SLUGS = new Set(['flash-sale']);

function HomeProductSection({ section, products, variant, tone }) {
  const title = section?.name || 'Collection';
  const description = section?.description?.trim() || '';
  const slug = section?.slug || '';
  const sectionStyle = tone === 'muted' ? { background: 'var(--bg)' } : undefined;
  const sectionClass = tone === 'white' ? 'section-padding bg-white' : 'section-padding';

  return (
    <section className={sectionClass} style={sectionStyle} data-aos="fade-up">
      <div className="container">
        <div className="d-flex justify-content-between align-items-end mb-5">
          <div>
            <h2 className="section-title mb-0">{title}</h2>
            <div className="gold-line gold-line-left" />
            {description ? <p className="section-subtitle mb-0">{description}</p> : null}
          </div>
          {slug ? (
            <Link
              to={`/shop?section=${encodeURIComponent(slug)}`}
              className="small text-uppercase fw-medium text-decoration-none d-none d-md-inline"
            >
              View All →
            </Link>
          ) : null}
        </div>

        {variant === 'slider' ? (
          <Swiper
            className="product-slider"
            modules={[Navigation, Autoplay]}
            navigation
            watchOverflow
            spaceBetween={12}
            slidesPerView={1.35}
            autoplay={{ delay: 4000, disableOnInteraction: false }}
            breakpoints={{
              480: { slidesPerView: 2, spaceBetween: 16 },
              768: { slidesPerView: 3, spaceBetween: 20 },
              1024: { slidesPerView: 4, spaceBetween: 24 },
            }}
          >
            {products.map((product, i) => (
              <SwiperSlide key={product.id}>
                <ProductCard product={product} index={i} />
              </SwiperSlide>
            ))}
          </Swiper>
        ) : (
          <div className="row g-4">
            {products.map((product, i) => (
              <div key={product.id} className="col-6 col-lg-3">
                <ProductCard product={product} index={i} />
              </div>
            ))}
          </div>
        )}
      </div>
    </section>
  );
}

export default function DynamicHomeSections() {
  const [rows, setRows] = useState([]);
  const [loaded, setLoaded] = useState(false);

  useEffect(() => {
    let cancelled = false;

    homeSectionService
      .getAll()
      .then(async (res) => {
        const sections = (res.data?.data || []).filter(
          (section) => section?.slug && !SPECIAL_SLUGS.has(section.slug)
        );
        const packed = await Promise.all(
          sections.map(async (section) => {
            const productsRes = await productService.getProducts({
              section: section.slug,
              per_page: 8,
            });
            return { section, products: productsRes.data?.data || [] };
          })
        );
        if (!cancelled) {
          setRows(packed.filter((row) => row.products.length > 0));
        }
      })
      .catch(() => {
        if (!cancelled) setRows([]);
      })
      .finally(() => {
        if (!cancelled) setLoaded(true);
      });

    return () => {
      cancelled = true;
    };
  }, []);

  if (!loaded || rows.length === 0) return null;

  return rows.map(({ section, products }, index) => (
    <HomeProductSection
      key={section.id || section.slug}
      section={section}
      products={products}
      variant={section.slug === 'trending' ? 'slider' : 'grid'}
      tone={index % 2 === 0 ? 'white' : 'muted'}
    />
  ));
}
