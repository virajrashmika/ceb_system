/* ---------------------------------------------------------------------------
   Settings agreed on 8 October 2026, when the office database was brought
   up to date with the new features.

     - 1400 kVA added to the capacity list.
     - Medium Voltage (MV) switched off as a work type. Old MV events keep
       their type; it is simply no longer offered for new ones.
     - "Conversion" added as a work detail under Retail Connection (RC).

   Safe to run again and again: nothing is added twice.

     mysql -u root ceb_system < sql/2026-10-08_settings.sql
   --------------------------------------------------------------------------- */

INSERT INTO capacities (kva)
SELECT 1400 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM capacities WHERE kva = 1400);

UPDATE task_types SET is_active = 0 WHERE type_code = 'MV';

INSERT INTO task_subtypes (type_id, subtype_name, is_active, needs_capacity)
SELECT t.type_id, 'Conversion', 1, 0
FROM task_types t
WHERE t.type_code = 'RC'
  AND NOT EXISTS (SELECT 1 FROM task_subtypes s
                  WHERE s.type_id = t.type_id AND s.subtype_name = 'Conversion');
