import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { categoryService } from '../../services/productService';
import { getStoredJson, resolveMediaUrl, setStoredJson } from '../../utils/helpers';

const CATEGORY_CACHE_KEY = 'vibhaa.homeCategories';

function flattenCategories(items = []) {
  return items
    .filter((cat) => !cat.parent_id)
    .map((cat) => ({
      ...cat,
      image: resolveMediaUrl(cat.image || cat.image_path || ''),
    }))
    .filter((cat) => cat.image);
}

function readCachedCategories() {
  const cached = getStoredJson(CATEGORY_CACHE_KEY, []);
  return Array.isArray(cached) ? cached.filter((c) => c?.image) : [];
}

export default function Categories() {
  const [categories, setCategories] = useState(readCachedCategories);

  useEffect(() => {
    categoryService.getCategories()
      .then((res) => {
        const data = res.data?.data ?? [];
        const list = flattenCategories(Array.isArray(data) ? data : []);
        setCategories(list);
        if (list.length) setStoredJson(CATEGORY_CACHE_KEY, list);
      })
      .catch(() => {
        // Keep cached categories if we already have them
      });
  }, []);

  if (!categories.length) return null;

  return (
    <section className="category-grid-section">
      <div className="container-fluid">
        <h2 className="category-grid__heading">Shop By Category</h2>

        <div className="category-grid">
          {categories.map((cat) => (
            <Link
              key={cat.id}
              to={`/shop?category_id=${cat.id}`}
              className="category-card"
            >
              <div className="category-card__media">
                <img src={cat.image} alt={cat.name} />
              </div>
              <span className="category-card__meta">
                <span className="category-card__name">{cat.name}</span>
                <span className="category-card__explore">
                  Explore <span aria-hidden="true">→</span>
                </span>
              </span>
            </Link>
          ))}
        </div>
      </div>
    </section>
  );
}
