-- Migration 051: tables for two public pages that crashed in production testing
--   faq.php / admin/returns-faq-management.php  -> faqs
--   contact.php / admin/contact-management.php  -> contact_messages
CREATE TABLE IF NOT EXISTS faqs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    question    VARCHAR(255) NOT NULL,
    answer      TEXT NOT NULL,
    category    VARCHAR(80) NULL,
    sort_order  INT NOT NULL DEFAULT 0,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_faq_active (is_active, category, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contact_messages (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NULL,
    name        VARCHAR(120) NOT NULL,
    email       VARCHAR(190) NOT NULL,
    phone       VARCHAR(40) NULL,
    subject     VARCHAR(200) NOT NULL,
    message     TEXT NOT NULL,
    department  VARCHAR(60) NULL,
    status      ENUM('new','read','replied','closed') NOT NULL DEFAULT 'new',
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_cm_status (status, created_at),
    KEY idx_cm_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Starter FAQs (only if the table is empty). Edit in Admin → Returns/FAQ management.
INSERT INTO faqs (question, answer, category, sort_order)
SELECT * FROM (
    SELECT 'How do I track my order?' AS q, 'Go to My Orders and open the order. Each seller''s shipment shows its own status and tracking events.' AS a, 'Orders' AS c, 1 AS s
    UNION ALL SELECT 'Can one order contain products from different sellers?', 'Yes. Your order is split per seller; each seller ships their part separately and you can track each shipment in the order details.', 'Orders', 2
    UNION ALL SELECT 'Which payment methods do you accept?', 'PayPal (cards and PayPal balance) and OXXO cash payments. An OXXO voucher is valid for a limited time; your items are reserved until it expires.', 'Payments', 1
    UNION ALL SELECT 'How is shipping calculated?', 'By package weight (or volumetric weight, if larger) and delivery distance from our warehouse. You see the exact cost at checkout before paying.', 'Shipping', 1
    UNION ALL SELECT 'What is your return policy?', 'Most products can be returned within 30 days of purchase; some products show their own return window on the product page. Request a return from My Orders.', 'Returns', 1
    UNION ALL SELECT 'There is a problem with my order. What can I do?', 'Open the order and click "Report a problem". The seller has 3 days to respond; if you cannot agree, VentDepot reviews the case and decides.', 'Returns', 2
    UNION ALL SELECT 'How do I contact a seller?', 'Use the "Contact Seller" button on any product page. Messages stay on VentDepot so we can help if there is a dispute.', 'Account', 1
    UNION ALL SELECT 'How can I sell on VentDepot?', 'Create a seller account from "Become a seller". After approval you can list products, build your store page and receive payouts for your sales.', 'Selling', 1
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM faqs);
