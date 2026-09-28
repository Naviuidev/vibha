import LegalPage, { LegalSection } from '../../components/legal/LegalPage';
import { BRAND_NAME } from '../../utils/constants';

export default function ReturnsPolicy() {
  return (
    <LegalPage
      title="Returns & Refunds Policy"
      description={`${BRAND_NAME} returns and refunds policy — no change-of-mind returns; replacement only for damaged or incorrect products.`}
      updated="20 September 2026"
    >
      <p className="text-muted mb-4">
        At {BRAND_NAME}, we carefully check every order before dispatch to ensure it reaches you in
        good condition.
      </p>

      <LegalSection title="No Returns or Refunds">
        <p>
          We do not accept returns or provide refunds for change of mind, wrong selection, or any
          other personal reason.
        </p>
      </LegalSection>

      <LegalSection title="Damaged or Incorrect Products">
        <p>
          If you receive a damaged or incorrect product, please contact us as soon as possible after
          delivery.
        </p>
      </LegalSection>

      <LegalSection title="Unboxing Video Required">
        <p>
          A single, continuous 360° unboxing video without cuts or edits is mandatory to verify any
          damage or incorrect product claim. Claims without a proper unboxing video may not be
          accepted.
        </p>
      </LegalSection>

      <LegalSection title="Replacement Only">
        <p>
          Once the issue is verified and approved, we will provide a replacement with the same
          product, subject to availability. Cash refunds will not be provided.
        </p>
      </LegalSection>

      <LegalSection title="Used or Altered Products">
        <p>
          Products that have been used, worn, damaged after delivery, or altered in any way will not
          be eligible for replacement.
        </p>
      </LegalSection>

      <p className="text-muted mb-0">
        Please contact our customer support team promptly if you receive a damaged or incorrect
        product.
      </p>
    </LegalPage>
  );
}
