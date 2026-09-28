import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { getStoredJson, resolveMediaUrl, setStoredJson } from '../../utils/helpers';
import api from '../../services/api';

const FEATURED_CACHE_KEY = 'vibhaa.featuredCollections';

function mapFeatured(rows) {
  return (Array.isArray(rows) ? rows : [])
    .map((r) => ({
      id: r.id,
      title: r.title,
      image: resolveMediaUrl(r.image),
      link: r.link || '/shop',
      cta_text: r.cta_text || null,
    }))
    .filter((r) => r.image);
}

function readCachedFeatured() {
  const cached = getStoredJson(FEATURED_CACHE_KEY, []);
  return Array.isArray(cached) ? cached.filter((r) => r?.image) : [];
}

export default function FeaturedCollection() {
  const [items, setItems] = useState(readCachedFeatured);

  useEffect(() => {
    let cancelled = false;

    (async () => {
      try {
        const { data } = await api.get('/featured-collections');
        const rows = mapFeatured(data?.data ?? []);
        if (cancelled) return;
        setItems(rows);
        if (rows.length) setStoredJson(FEATURED_CACHE_KEY, rows);
      } catch {
        // Keep cached tiles if we already have them
      }
    })();

    return () => {
      cancelled = true;
    };
  }, []);

  const main = items[0];
  const side = items.slice(1, 3);

  if (!main) return null;

  return (
    <section className="featured-collection section-padding" data-aos="fade-up">
      <div className="container-fluid px-4">
        <div className="text-center mb-5">
          <h2 className="section-title">Featured Collection</h2>
          <div className="gold-line" />
          <p className="section-subtitle">Curated jewellery for every occasion</p>
        </div>
        <div className="featured-collection__grid">
          <Link to={main.link || '/shop'} className="featured-collection__main text-decoration-none">
            <img src={main.image} alt={main.title} />
            <div className="featured-collection__label">
              <h3>{main.title}</h3>
              {main.cta_text ? <span className="small opacity-75">{main.cta_text}</span> : null}
            </div>
          </Link>
          <div className="featured-collection__side">
            {side.map((item) => (
              <Link
                key={item.id}
                to={item.link || '/shop'}
                className="featured-collection__item text-decoration-none"
              >
                <img src={item.image} alt={item.title} />
                <div className="featured-collection__label">
                  <h3>{item.title}</h3>
                </div>
              </Link>
            ))}
          </div>
        </div>
      </div>
    </section>
  );
}
