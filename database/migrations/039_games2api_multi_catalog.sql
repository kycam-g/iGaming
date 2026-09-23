-- Permite manter o mesmo jogo cadastrado em APIs diferentes sem apagar/substituir o catálogo anterior.
ALTER TABLE casino_games
  DROP INDEX uq_casino_game_external,
  ADD UNIQUE KEY uq_casino_game_external_source (provider_id, external_id, api_source);
