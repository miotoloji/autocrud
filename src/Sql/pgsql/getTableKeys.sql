SELECT
  tc.CONSTRAINT_NAME,
  tc.TABLE_NAME,
  kcu.COLUMN_NAME,
  ordinal_position,
  position_in_unique_constraint,
  constraint_type,
  (SELECT TABLE_NAME FROM information_schema.constraint_column_usage ccu WHERE ccu.CONSTRAINT_NAME = tc.CONSTRAINT_NAME LIMIT 1) AS foreign_table_name,
  (SELECT COLUMN_NAME FROM information_schema.constraint_column_usage ccu WHERE ccu.CONSTRAINT_NAME = tc.CONSTRAINT_NAME LIMIT 1) AS foreign_column_name 
FROM
  information_schema.table_constraints AS tc
  JOIN information_schema.key_column_usage AS kcu ON tc.CONSTRAINT_NAME = kcu.
  CONSTRAINT_NAME
  AND tc.table_schema = kcu.table_schema 
WHERE
  tc.TABLE_NAME = '{%TABLENAME%}';