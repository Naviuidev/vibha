import LegalPage, { LegalSection } from '../../components/legal/LegalPage';
import { BRAND_NAME } from '../../utils/constants';

export default function ShippingPolicy() {
  return (
    <LegalPage
      title="Shipping Policy"
      description={`${BRAND_NAME} shipping policy — free shipping across India, 10–14 working days delivery, and online payment only.`}
      updated="20 September 2026"
    >
      <LegalSection title="Free Shipping">
        <p>
          We offer FREE shipping across India on all orders placed through our website.
        </p>
      </LegalSection>

      <LegalSection title="Delivery Time">
        <p>
          Orders are usually delivered within 10–14 working days from the date of order
          confirmation.
        </p>
        <p>
          Delivery time may vary depending on the customer&apos;s location, courier service,
          weather, or other unforeseen circumstances.
        </p>
      </LegalSection>

      <LegalSection title="Tracking">
        <p>
          Once your order is shipped, you will receive the available tracking details to track your
          package.
        </p>
      </LegalSection>

      <LegalSection title="Delivery Address">
        <p>
          Please ensure that the delivery address and contact details provided at checkout are
          correct.
        </p>
      </LegalSection>

      <LegalSection title="Payment">
        <p>
          Currently, we offer online payment only. Cash on Delivery (COD) is not available.
        </p>
      </LegalSection>
    </LegalPage>
  );
}
