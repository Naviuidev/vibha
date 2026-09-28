import { Link } from 'react-router-dom';
import SEO from '../../components/common/SEO';
import Breadcrumb from '../../components/common/Breadcrumb';
import Button from '../../components/ui/Button';
import { BRAND_NAME } from '../../utils/constants';

export default function About() {
  return (
    <>
      <SEO
        title="About Us"
        description="Vibhaa Jewellery — handcrafted jewellery that celebrates every occasion."
      />
      <div className="page-header">
        <div className="container">
          <Breadcrumb items={[{ label: 'About' }]} />
          <h1>About Us</h1>
        </div>
      </div>

      <section className="container py-5">
        <div className="row g-5 align-items-center">
          <div className="col-lg-6">
            <img
              src="/about.jpg"
              alt={`${BRAND_NAME} — Crafted to shine`}
              className="about-page__image w-100"
            />
          </div>
          <div className="col-lg-6">
            <h2 className="section-title">Sparkle in every moment.</h2>
            <div className="gold-line gold-line-left" />

            <p className="text-muted mb-4">
              Vibhaa Jewellery is a jewellery brand built around one simple idea — every piece should
              feel special, whether it is for everyday wear or a celebration.
            </p>
            <p className="text-muted mb-4">
              We bring together necklaces, earrings, rings, bracelets and more that combine
              traditional craftsmanship with contemporary design. Each collection is chosen for
              quality, finish and the way it sits with your style.
            </p>
            <p className="text-muted mb-4">
              We carefully select our pieces with an emphasis on design and value, so you can
              discover jewellery that feels premium without being out of reach.
            </p>
            <p className="text-muted mb-4">
              Whether you are looking for a gift or something to complete your look, Vibhaa Jewellery
              makes it easy to find pieces that shine with you.
            </p>
            <p className="fw-medium mb-4">
              Vibhaa Jewellery — sparkle in every moment.
            </p>

            <Link to="/shop"><Button>Explore Collection</Button></Link>
          </div>
        </div>
      </section>

      <section className="py-5" style={{ background: 'var(--bg)' }}>
        <div className="container">
          <div className="row g-4 text-center">
            {[
              { num: '50K+', label: 'Happy Customers' },
              { num: '500+', label: 'Premium Products' },
              { num: '30', label: 'Day Returns' },
              { num: '24/7', label: 'Support' },
            ].map((stat) => (
              <div key={stat.label} className="col-6 col-md-3">
                <div className="display-6 fw-semibold">{stat.num}</div>
                <div className="small text-uppercase text-muted">{stat.label}</div>
              </div>
            ))}
          </div>
        </div>
      </section>
    </>
  );
}
