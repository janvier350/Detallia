-- Migracion 18: corregir clientes importados desde lotes de Excel (validacion)
--
-- En esos lotes, pending_clients.name = persona y pending_clients.contact_name = empresa,
-- que es lo opuesto a como Clientes usa las columnas (name = empresa, contact_name = persona).
-- Los clientes ya importados quedaron con empresa y contacto intercambiados.
--
-- Este UPDATE intercambia name <-> contact_name SOLO en los clientes que provienen de un lote
-- de validacion ya importado, emparejando por los valores originales. Es seguro correrlo una
-- sola vez: tras el intercambio la condicion de emparejamiento deja de cumplirse, por lo que
-- volver a ejecutarlo no vuelve a intercambiar.

UPDATE clients c
JOIN pending_clients p
  ON p.name = c.name
 AND COALESCE(p.contact_name, '') = COALESCE(c.contact_name, '')
JOIN validation_links vl
  ON vl.id = p.link_id
SET c.name = p.contact_name,
    c.contact_name = p.name
WHERE vl.mode = 'validacion'
  AND p.imported = 1
  AND p.contact_name IS NOT NULL
  AND p.contact_name <> '';
