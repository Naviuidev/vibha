const STATS = [
  { num: '27K+', label: 'Clients' },
  { num: '25K+', label: 'Premium Products' },
  { num: '✓', label: 'Quality Checked Jewellery' },
  { num: '24/7', label: 'Jewellery' },
];

export default function HomeStats() {
  return (
    <section className="home-stats" aria-label="Vibhaa Jewellery at a glance">
      <div className="container">
        <div className="home-stats__grid">
          {STATS.map((stat) => (
            <div key={stat.label} className="home-stats__item">
              <div className="home-stats__num">{stat.num}</div>
              <div className="home-stats__label">{stat.label}</div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}
