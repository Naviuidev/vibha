import LegalPage, { LegalSection } from '../../components/legal/LegalPage';
import { BRAND_NAME } from '../../utils/constants';

export default function PrivacyPolicy() {
  return (
    <LegalPage
      title="Privacy Policy"
      description={`${BRAND_NAME} privacy policy — how we collect, use, and protect your information.`}
      updated="20 September 2026"
    >
      <p className="text-muted mb-4">
        At {BRAND_NAME}, we respect your privacy and are committed to protecting your personal
        information. This Privacy Policy explains how we collect, use, and protect your information
        when you visit or shop on our website.
      </p>

      <LegalSection title="1. Information We Collect">
        <p>When you place an order or contact us, we may collect information such as:</p>
        <ul>
          <li>Name</li>
          <li>Mobile number</li>
          <li>Email address</li>
          <li>Delivery address</li>
          <li>Billing details</li>
          <li>Order and payment information</li>
        </ul>
      </LegalSection>

      <LegalSection title="2. How We Use Your Information">
        <p>We use your information to:</p>
        <ul>
          <li>Process and deliver your orders</li>
          <li>Provide order updates and customer support</li>
          <li>Process payments securely</li>
          <li>Handle replacement or service requests</li>
          <li>Improve our products, services, and website</li>
          <li>Contact you regarding your orders or important service updates</li>
        </ul>
      </LegalSection>

      <LegalSection title="3. Payment Information">
        <p>
          Payments are processed through secure third-party payment providers. {BRAND_NAME} does not
          directly store your complete card, UPI, or banking credentials.
        </p>
      </LegalSection>

      <LegalSection title="4. Sharing of Information">
        <p>We do not sell or rent your personal information to third parties.</p>
        <p>
          We may share necessary information with trusted service providers, such as courier and
          payment service providers, only when required to process your order or provide our
          services.
        </p>
      </LegalSection>

      <LegalSection title="5. Data Security">
        <p>
          We take reasonable measures to protect your personal information from unauthorized access,
          misuse, alteration, or disclosure. However, no online system can guarantee complete
          security.
        </p>
      </LegalSection>

      <LegalSection title="6. Cookies">
        <p>
          Our website may use cookies and similar technologies to improve your browsing experience,
          understand website usage, and provide essential website functionality.
        </p>
      </LegalSection>

      <LegalSection title="7. Third-Party Services">
        <p>
          Our website may use third-party services such as payment gateways, courier services,
          analytics tools, or other service providers. These services may have their own privacy
          policies.
        </p>
      </LegalSection>

      <LegalSection title="8. Your Information">
        <p>
          You may contact us if you want to know what personal information we hold about you or if
          you believe your information needs to be corrected or updated.
        </p>
      </LegalSection>

      <LegalSection title="9. Changes to This Privacy Policy">
        <p>
          We may update this Privacy Policy from time to time. Any changes will be published on this
          page with the updated date.
        </p>
      </LegalSection>

      <LegalSection title="10. Contact Us">
        <p>
          If you have any questions regarding this Privacy Policy or how your information is
          handled, please contact {BRAND_NAME} through the contact details provided on our website.
        </p>
      </LegalSection>
    </LegalPage>
  );
}
