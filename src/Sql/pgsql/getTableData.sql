SELECT
  column_name,
  is_nullable,
  character_maximum_length,
  numeric_precision,
  numeric_precision_radix,
  udt_name,
  ets.typs
FROM
  information_schema.
  COLUMNS LEFT JOIN (
    SELECT T
      .typname,
      string_agg ( e.enumlabel, ',' ) AS typs
    FROM
      pg_type
      T JOIN pg_enum e ON T.OID = e.enumtypid
      JOIN pg_catalog.pg_namespace n ON n.OID = T.typnamespace
    GROUP BY
      1
  ) ets ON ets.typname = information_schema.COLUMNS.udt_name
WHERE
  "table_name" = '{%TABLENAME%}'