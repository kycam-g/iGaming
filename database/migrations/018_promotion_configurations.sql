-- Cadastro administrativo; configurações são inativas e NÃO acionam créditos automáticos.
CREATE TABLE IF NOT EXISTS promotion_configurations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 type VARCHAR(24) NOT NULL,
 title VARCHAR(120) NOT NULL,
 config JSON NOT NULL,
 enabled TINYINT(1) NOT NULL DEFAULT 0,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 INDEX idx_promotion_configurations_type (type,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- Seed idempotente por migration: 20 níveis; VIP 6–20 têm bônus zero até configuração.
INSERT INTO promotion_configurations (type,title,config,enabled) VALUES
('vip','VIP 1','{"level":1,"goal_cents":500000,"bonus_cents":5000,"rollover_x":0}',0),
('vip','VIP 2','{"level":2,"goal_cents":1800000,"bonus_cents":18000,"rollover_x":0}',0),
('vip','VIP 3','{"level":3,"goal_cents":10000000,"bonus_cents":100000,"rollover_x":0}',0),
('vip','VIP 4','{"level":4,"goal_cents":20000000,"bonus_cents":200000,"rollover_x":0}',0),
('vip','VIP 5','{"level":5,"goal_cents":100000000,"bonus_cents":500000,"rollover_x":0}',0),
('vip','VIP 6','{"level":6,"goal_cents":200000000,"bonus_cents":0,"rollover_x":0}',0),
('vip','VIP 7','{"level":7,"goal_cents":300000000,"bonus_cents":0,"rollover_x":0}',0),
('vip','VIP 8','{"level":8,"goal_cents":400000000,"bonus_cents":0,"rollover_x":0}',0),
('vip','VIP 9','{"level":9,"goal_cents":500000000,"bonus_cents":0,"rollover_x":0}',0),
('vip','VIP 10','{"level":10,"goal_cents":600000000,"bonus_cents":0,"rollover_x":0}',0),
('vip','VIP 11','{"level":11,"goal_cents":700000000,"bonus_cents":0,"rollover_x":0}',0),
('vip','VIP 12','{"level":12,"goal_cents":800000000,"bonus_cents":0,"rollover_x":0}',0),
('vip','VIP 13','{"level":13,"goal_cents":900000000,"bonus_cents":0,"rollover_x":0}',0),
('vip','VIP 14','{"level":14,"goal_cents":1000000000,"bonus_cents":0,"rollover_x":0}',0),
('vip','VIP 15','{"level":15,"goal_cents":1100000000,"bonus_cents":0,"rollover_x":0}',0),
('vip','VIP 16','{"level":16,"goal_cents":1200000000,"bonus_cents":0,"rollover_x":0}',0),
('vip','VIP 17','{"level":17,"goal_cents":1300000000,"bonus_cents":0,"rollover_x":0}',0),
('vip','VIP 18','{"level":18,"goal_cents":1400000000,"bonus_cents":0,"rollover_x":0}',0),
('vip','VIP 19','{"level":19,"goal_cents":1500000000,"bonus_cents":0,"rollover_x":0}',0),
('vip','VIP 20','{"level":20,"goal_cents":1600000000,"bonus_cents":0,"rollover_x":0}',0);
