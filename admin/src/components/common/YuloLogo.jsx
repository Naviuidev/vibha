/**
 * Vibhaa Jewellery logo.
 * variant: 'light' | 'dark' | 'accent' — same mark (square, gold on teal)
 */
const SRC = {
  light: '/logo-light.png',
  dark: '/logo-dark.png',
  accent: '/logo-dark.png',
};

export default function YuloLogo({
  variant = 'dark',
  className = '',
  title = 'Vibhaa Jewellery',
}) {
  const src = `${SRC[variant] || SRC.dark}?v=vibhaa`;

  return (
    <img
      src={src}
      alt={title}
      className={className}
      decoding="async"
    />
  );
}
