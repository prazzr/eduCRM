ALTER TABLE email_template_channels ADD COLUMN gateway_id INT(11) NULL AFTER template_id;
ALTER TABLE email_template_channels ADD CONSTRAINT fk_template_gateway FOREIGN KEY (gateway_id) REFERENCES messaging_gateways(id) ON DELETE CASCADE;
