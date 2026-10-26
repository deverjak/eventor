type ObjectValue = Record<string, unknown>;
const constraints: Record<string, string[]> = {
  short_text: ["minLength", "maxLength"],
  long_text: ["minLength", "maxLength"],
  number: ["min", "max", "integer"],
  date: ["minDate", "maxDate", "minAge", "maxAge", "referenceDate"],
  single_choice: ["choices"],
  multi_choice: ["choices", "minChoices", "maxChoices"],
  legal_requirement: ["requirementId"],
};
/** Keep only constraints accepted for the selected field type; retain explicit false and zero. */
export function commandPayload(path: string, input: ObjectValue): ObjectValue {
  const body = structuredClone(input);
  if (path.endsWith("/form/draft") && Array.isArray(body.fields)) {
    body.fields = body.fields.map((raw: ObjectValue) => {
      const allowed = constraints[String(raw.type)] || [];
      const limits =
        raw.constraints && typeof raw.constraints === "object"
          ? (raw.constraints as ObjectValue)
          : {};
      const condition = raw.condition as ObjectValue | null;
      return {
        ...raw,
        constraints: Object.fromEntries(
          Object.entries(limits).filter(
            ([k, v]) =>
              allowed.includes(k) &&
              v != null &&
              v !== "" &&
              (!Array.isArray(v) || v.length),
          ),
        ),
        condition: condition?.type ? condition : null,
      };
    });
  }
  if (body.audience && typeof body.audience === "object") {
    const audience = body.audience as ObjectValue;
    if (audience.checkedIn === "true" || audience.checkedIn === "false")
      audience.checkedIn = audience.checkedIn === "true";
  }
  return body;
}

export function resolveActionPath(
  root: string,
  id: string,
  suffix: string | undefined,
  row: ObjectValue,
): string {
  return (
    suffix?.startsWith("/api/")
      ? suffix
      : `${root}/${encodeURIComponent(id)}${suffix || ""}`
  ).replace(/\{(\w+)\}/g, (_, key: string) => {
    const value = row[key];
    if (typeof value !== "string" || !value)
      throw new Error(`Chybí identifikátor ${key}. Obnovte záznam.`);
    return encodeURIComponent(value);
  });
}
