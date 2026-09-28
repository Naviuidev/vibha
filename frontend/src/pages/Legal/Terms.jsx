import LegalPage, { LegalSection } from '../../components/legal/LegalPage';
import { BRAND_NAME } from '../../utils/constants';

export default function Terms() {
  return (
    <LegalPage
      title="Terms & Conditions"
      description={`${BRAND_NAME} terms and conditions for using our website and placing orders.`}
      updated="20 September 2026"
    >
      <p className="text-muted mb-4">
        Welcome to {BRAND_NAME}. By accessing or using our website and placing an order, you agree to
        the following Terms &amp; Conditions.
      </p>

      <LegalSection title="1. Orders & Payments">
        <ul>
          <li>All orders are subject to product availability and confirmation.</li>
          <li>We currently accept online payments only. Cash on Delivery (COD) is not available.</li>
          <li>Orders are confirmed only after successful payment.</li>
          <li>Once an order is confirmed, cancellation may not be possible.</li>
        </ul>
      </LegalSection>

      <LegalSection title="2. Pricing & Shipping">
        <ul>
          <li>Product prices are displayed on our website and may be changed without prior notice.</li>
          <li>FREE shipping is available on every order placed through our website across India.</li>
          <li>Any applicable taxes or additional charges will be displayed during checkout.</li>
        </ul>
      </LegalSection>

      <LegalSection title="3. Shipping & Delivery">
        <ul>
          <li>Orders are generally delivered within 10–14 working days after order confirmation.</li>
          <li>
            Delivery timelines may vary depending on the delivery location, courier service, weather
            conditions, or other unforeseen circumstances.
          </li>
          <li>
            Customers are responsible for providing a correct and complete delivery address and
            contact details.
          </li>
          <li>
            Once the order is handed over to the courier, delivery is subject to the courier
            company&apos;s terms and serviceability.
          </li>
          <li>Tracking details will be shared once the order is shipped, where available.</li>
        </ul>
      </LegalSection>

      <LegalSection title="4. Returns, Refunds & Replacements">
        <ul>
          <li>
            We do not accept returns or provide refunds for change of mind, wrong selection, or
            personal preference.
          </li>
          <li>
            If you receive a damaged or incorrect product, you must contact us promptly after
            delivery.
          </li>
          <li>
            A single, continuous 360° unboxing video without cuts or edits is required for any
            damage or incorrect-product claim.
          </li>
          <li>
            After verification and approval, eligible claims will be handled through replacement
            with the same product, subject to availability.
          </li>
          <li>Cash refunds are not provided for approved damage or incorrect-product claims.</li>
          <li>
            Replacement shipping charges, if applicable, will be communicated to the customer
            before the replacement is processed.
          </li>
          <li>
            Products that have been worn, used, altered, or damaged after delivery are not eligible
            for replacement.
          </li>
        </ul>
      </LegalSection>

      <LegalSection title="5. Product Information">
        <ul>
          <li>We make reasonable efforts to display accurate product images, descriptions, and prices.</li>
          <li>
            Due to lighting, photography, screen settings, and manufacturing variations, the actual
            product may have slight differences from the images shown on the website.
          </li>
        </ul>
      </LegalSection>

      <LegalSection title="6. Customer Responsibility">
        <p>
          Customers are responsible for providing accurate contact and delivery information while
          placing an order. {BRAND_NAME} is not responsible for delays or failed deliveries caused
          by incorrect or incomplete information provided by the customer.
        </p>
      </LegalSection>

      <LegalSection title="7. Website Use">
        <p>
          You agree to use our website only for lawful purposes. You must not misuse the website,
          attempt unauthorized access, or interfere with its normal operation.
        </p>
      </LegalSection>

      <LegalSection title="8. Intellectual Property">
        <p>
          All website content, including the {BRAND_NAME} name, logo, product images, text,
          graphics, and other original content, belongs to {BRAND_NAME} or its respective owners
          and may not be copied or used without permission.
        </p>
      </LegalSection>

      <LegalSection title="9. Policy Updates">
        <p>
          {BRAND_NAME} reserves the right to update or modify these Terms &amp; Conditions at any
          time. Updated terms will be published on this page.
        </p>
      </LegalSection>

      <LegalSection title="10. Contact Us">
        <p>
          For questions regarding these Terms &amp; Conditions, orders, or customer support, please
          contact {BRAND_NAME} through the contact details provided on our website.
        </p>
      </LegalSection>

      <p className="text-muted mb-0">
        By placing an order on our website, you acknowledge that you have read and agreed to these
        Terms &amp; Conditions.
      </p>
    </LegalPage>
  );
}
