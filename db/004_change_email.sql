-- El. pašto keitimas: patvirtinimo nuoroda siunčiama į NAUJĄ adresą
ALTER TABLE email_tokens MODIFY purpose ENUM('verify_email','parent_consent','password_reset','invite','change_email') NOT NULL;
