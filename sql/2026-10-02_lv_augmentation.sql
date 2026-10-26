/* ---------------------------------------------------------------------------
   Augmentation — a new work detail under Low Voltage (LV).

   Ticking it on Add Event / Edit Event brings up the New Capacity box, the
   same way "New Transformer" does, because needs_capacity is 1. When the
   event is marked Completed, the transformer takes the new capacity.

   Safe to run again and again: if Low Voltage already has an Augmentation
   option, nothing is added.

     mysql -u root ceb_system < sql/2026-10-02_lv_augmentation.sql
   --------------------------------------------------------------------------- */

INSERT INTO task_subtypes (type_id, subtype_name, is_active, needs_capacity)
SELECT t.type_id, 'Augmentation', 1, 1
FROM task_types t
WHERE t.type_code = 'LV'
  AND NOT EXISTS (SELECT 1 FROM task_subtypes s
                  WHERE s.type_id = t.type_id AND s.subtype_name = 'Augmentation');
