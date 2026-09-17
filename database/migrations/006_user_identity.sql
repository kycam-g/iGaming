ALTER TABLE users
    ADD COLUMN cpf VARCHAR(11) NULL AFTER email,
    ADD COLUMN phone VARCHAR(11) NULL AFTER cpf,
    ADD UNIQUE KEY uq_users_cpf (cpf),
    ADD UNIQUE KEY uq_users_phone (phone);
