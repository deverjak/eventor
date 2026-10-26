import type { Action, Field, ResourceSpec } from "./workspace-ui";

export const managers = ["OWNER", "EVENT_MANAGER"];
export const finance = ["OWNER", "FINANCE"];
export const f = (
  key: string,
  label: string,
  type = "text-input",
  extra: Partial<Field> = {},
): Field => {
  const dateTime = [
    "startsAt",
    "endsAt",
    "registrationOpensAt",
    "registrationClosesAt",
    "selfServiceCancellationDeadline",
    "expiresAt",
    "at",
  ].includes(key);
  return {
    key,
    label: label
      .replace(" (ISO 8601)", "")
      .replace(" (včetně časového pásma)", ""),
    ...(dateTime
      ? {
          type: "datetime-local",
          help: "Čas v místním pásmu vašeho prohlížeče.",
        }
      : type !== "text-input"
        ? { type }
        : {}),
    ...extra,
  };
};
export const required = (key: string, label: string, type = "text-input") =>
  f(key, label, type, { required: true });
export const choice = (
  key: string,
  label: string,
  options: string[],
  value?: string,
) => f(key, label, "text-input", { options, value });
export const obj = (key: string, label: string, fields: Field[]) =>
  f(key, label, "object", { fields });
export const arr = (key: string, label: string, fields: Field[]) =>
  f(key, label, "array", { fields });
export const reason = [required("reason", "Důvod")];
export const money = obj("price", "Cena", [
  f("minor", "Cena v haléřích", "integer", {
    required: true,
    value: 0,
    help: "100 haléřů = 1 Kč",
  }),
  f("currency", "Měna", "text-input", { value: "CZK", required: true }),
]);
export const eventFields: Field[] = [
  required("name", "Název akce"),
  required("startsAt", "Začátek (včetně časového pásma)", "text-input"),
  required("endsAt", "Konec (včetně časového pásma)"),
  f("timezone", "Časové pásmo", "text-input", { value: "Europe/Prague" }),
  f("shortDescription", "Krátký popis"),
  f("description", "Popis", "text"),
  f("coverImageUrl", "Titulní obrázek", "url"),
  f("contactName", "Kontaktní osoba"),
  f("contactEmail", "Kontaktní e-mail", "email"),
  f("contactPhone", "Telefon", "tel"),
  choice("visibility", "Viditelnost", ["public", "unlisted"], "public"),
  f("ageReferenceDate", "Datum pro výpočet věku", "date"),
  f("registrationOpensAt", "Otevření registrací (ISO 8601)"),
  f("registrationClosesAt", "Uzavření registrací (ISO 8601)"),
  arr("venues", "Místa konání", [
    required("name", "Název místa"),
    f("address", "Adresa"),
    f("mapUrl", "Odkaz na mapu", "url"),
  ]),
];
export const createEventFields = [
  obj("details", "Údaje akce", eventFields),
  choice("mode", "Typ akce", ["simple", "multi"], "simple"),
  money,
  f("capacityLimit", "Kapacita (prázdné = neomezená)", "integer"),
];
export const activityFields = [
  required("name", "Název aktivity"),
  f("description", "Popis", "text"),
  f("location", "Místo"),
  f("startsAt", "Začátek (ISO 8601)"),
  f("endsAt", "Konec (ISO 8601)"),
  f("registrationOpensAt", "Otevření registrací (ISO 8601)"),
  f("registrationClosesAt", "Uzavření registrací (ISO 8601)"),
  f("minAge", "Minimální věk", "integer"),
  f("maxAge", "Maximální věk", "integer"),
  f("minorsOnly", "Pouze nezletilí", "boolean"),
  f("capacityLimit", "Kapacita", "integer"),
  f("important", "Významná změna", "boolean"),
];
export const variantFields = [
  required("name", "Název varianty"),
  money,
  f("teamSize", "Velikost týmu", "integer"),
  f("minAge", "Minimální věk", "integer"),
  f("maxAge", "Maximální věk", "integer"),
  f("capacityLimit", "Počáteční kapacita", "integer"),
];
export const paymentRules = [
  f("methods", "Povolené metody: bank_transfer, cash", "strings"),
  f("dueDays", "Splatnost ve dnech", "integer", { value: 7 }),
  f("holdTtlHours", "Rezervace v hodinách", "integer", { value: 48 }),
  f("autoRestoreOnLatePayment", "Obnovit při pozdní platbě", "boolean"),
  f(
    "selfServiceCancellationAllowed",
    "Povolit samoobslužné storno",
    "boolean",
    { value: true },
  ),
  f("selfServiceCancellationDeadline", "Storno nejpozději (ISO 8601)"),
];
export const registrationActions: Action[] = [
  "open",
  "pause",
  "resume",
  "close",
].map((transition, i) => ({
  label: [
    "Otevřít registrace",
    "Pozastavit registrace",
    "Obnovit registrace",
    "Uzavřít registrace",
  ][i],
  path: `/registration/${transition}`,
  fields: [
    f("reason", "Důvod"),
    f("cascade", "Použít i na aktivity", "boolean"),
  ],
  roles: managers,
  version: true,
}));
export const profileFields = [
  required("name", "Název organizátora"),
  f("contactEmail", "Kontaktní e-mail", "email"),
  f("contactPhone", "Telefon"),
  f("legalName", "Obchodní název"),
  f("companyId", "IČO"),
  f("vatId", "DIČ"),
  f("billingStreet", "Ulice"),
  f("billingCity", "Město"),
  f("billingPostalCode", "PSČ"),
  f("billingCountry", "Kód země"),
  f("logoUrl", "Logo", "url"),
  f("coverImageUrl", "Titulní obrázek", "url"),
  f("brandColor", "Barva", "color"),
];
export const roleField = choice(
  "role",
  "Role",
  ["OWNER", "EVENT_MANAGER", "FINANCE", "CHECK_IN"],
  "EVENT_MANAGER",
);
export const memberFields = [
  roleField,
  f(
    "eventScope",
    "Přístup pouze k akcím (ID oddělená čárkou; prázdné = všechny)",
    "strings",
  ),
];
export const templateKeys = [
  "order_received",
  "payment_instructions",
  "payment_reminder",
  "payment_partial",
  "participation_confirmed",
  "reservation_expired",
  "participation_cancelled",
  "participation_changed",
  "participation_transferred",
  "pre_event_reminder",
  "activity_changed",
  "program_changed",
  "event_cancelled",
  "post_event",
  "answers_incomplete",
  "reconfirmation_request",
  "consent_withdrawn",
  "refund_settled",
  "self_service_link",
  "member_invitation",
  "news_broadcast",
  "document_changed",
  "custom",
];
export const textFields = [
  required("subject", "Předmět"),
  required("body", "Text zprávy", "text"),
];
export const templateFields = [
  choice("key", "Typ zprávy", templateKeys),
  choice("locale", "Jazyk", ["cs", "en", "de"], "cs"),
  ...textFields,
];
export const audienceFields = [
  f("activityIds", "ID aktivit", "strings"),
  f("variantIds", "ID variant", "strings"),
  f("participationStates", "Stavy účastí", "strings"),
  f("financialStates", "Platební stavy", "strings"),
  choice("checkedIn", "Odbavení", ["true", "false"]),
];
export const campaignFields = [
  required("name", "Název kampaně"),
  choice("purpose", "Účel", ["organizational", "marketing"], "organizational"),
  choice("locale", "Jazyk", ["cs", "en", "de"], "cs"),
  choice("templateKey", "Šablona", templateKeys),
  f("subject", "Předmět"),
  f("body", "Text", "text"),
  obj("audience", "Příjemci", audienceFields),
];
export const documentFields = [
  choice("level", "Úroveň", ["organizer", "event", "activity"], "organizer"),
  required("title", "Název dokumentu"),
  f("scopeId", "ID akce nebo aktivity (u organizátora prázdné)"),
  f("replacesDocumentId", "ID nahrazovaného dokumentu"),
];
export const versionFields = [
  choice("kind", "Obsah verze", ["text", "pdf", "external_snapshot"], "text"),
  f("text", "Text dokumentu", "text"),
  f("url", "Odkaz na dokument", "url"),
  f("pdfBase64", "Nahrát PDF", "pdf-file"),
];
export const requirementFields = [
  required("purpose", "Účel"),
  choice(
    "confirmer",
    "Kdo potvrzuje",
    ["orderer", "each_participant", "guardian"],
    "each_participant",
  ),
  f("mandatory", "Povinné potvrzení", "boolean"),
  f("revocable", "Odvolatelné", "boolean"),
  f("scopeText", "Rozsah", "text"),
  f("validity", "Platnost"),
  f("activityId", "ID aktivity"),
  f("variantId", "ID varianty"),
];
const filterFields = [
  f("page", "Stránka", "integer", { value: 1 }),
  f("limit", "Záznamů na stránku (max. 100)", "integer", { value: 25 }),
];

export function organizerSpecs(
  root: string,
  module: string,
  eventId: string,
): ResourceSpec[] {
  const events = `${root}/events`;
  const scopedMemberFields = memberFields.map((field) =>
    field.key === "eventScope"
      ? {
          ...field,
          label: "Přístup pouze k vybraným akcím (prázdné = všechny)",
          source: events,
        }
      : field,
  );
  const eventRef = f("eventId", "Akce", "text-input", {
    source: events,
    required: true,
    value: eventId || null,
  });
  if (module === "team")
    return [
      {
        title: "Členové týmu",
        path: `${root}/members`,
        idKey: "membershipId",
        fields: scopedMemberFields,
        edit: true,
        remove: true,
      },
      {
        title: "Pozvánky",
        path: `${root}/invitations`,
        idKey: "invitationId",
        create: {
          label: "Pozvat člena",
          fields: [required("email", "E-mail", "email"), ...scopedMemberFields],
        },
        remove: true,
      },
      {
        title: "Odbavovací zařízení",
        path: `${root}/device-tokens`,
        create: {
          label: "Přidat zařízení",
          fields: [
            required("label", "Název zařízení"),
            f("checkInListId", "ID odbavovacího seznamu (volitelné)"),
            f("expiresAt", "Platnost do (ISO 8601)"),
          ],
        },
        remove: true,
      },
    ];
  if (module === "settings")
    return [
      {
        title: "Profil organizátora",
        path: `${root}/profile`,
        fields: profileFields,
        edit: true,
      },
      {
        title: "Platební nastavení",
        path: `${root}/payment-settings`,
        edit: true,
        fields: [
          f("defaultDueDays", "Výchozí splatnost ve dnech", "integer", {
            value: 14,
          }),
          f("defaultHoldTtlHours", "Výchozí rezervace v hodinách", "integer", {
            value: 48,
          }),
          choice(
            "overpaymentHandling",
            "Přeplatky",
            ["refund", "donation", "ask"],
            "ask",
          ),
          f("autoRestoreLatePayment", "Obnova při pozdní platbě", "boolean"),
          f(
            "checkInMayRecordCash",
            "Odbavení smí přijímat hotovost",
            "boolean",
          ),
          f(
            "eventManagerMayResolvePayments",
            "Správce akcí smí řešit výjimky plateb",
            "boolean",
          ),
        ],
        actions: [
          {
            label: "Propojit bankovní účet",
            path: "/pairrot",
            fields: [
              required("accountNumberOrIban", "Číslo účtu nebo IBAN"),
              f("label", "Název", "text-input", { value: "Eventor" }),
            ],
          },
        ],
      },
      {
        title: "Stav bankovního propojení",
        path: `${root}/payments/connection`,
      },
    ];
  if (module === "communication")
    return [
      {
        title: "Kampaně",
        path: `${root}/campaigns`,
        create: { label: "Nová kampaň", fields: [eventRef, ...campaignFields] },
        edit: true,
        loadDetail: true,
        fields: campaignFields,
        actions: [
          { label: "Detail", read: true },
          { label: "Odeslat", path: "/send", danger: true },
          {
            label: "Naplánovat",
            path: "/schedule",
            fields: [required("at", "Odeslat v (ISO 8601)")],
          },
          { label: "Zrušit", path: "/cancel", danger: true },
          { label: "Doručení", path: "/deliveries", read: true },
          { label: "Opakovat neúspěšná doručení", path: "/deliveries/retry" },
        ],
      },
      {
        title: "Šablony",
        path: `${root}/message-templates`,
        create: { label: "Nová šablona", fields: templateFields },
        edit: true,
        loadDetail: true,
        fields: textFields,
        remove: true,
        actions: [{ label: "Detail", read: true }],
      },
      {
        title: "Historie doručení",
        path: `${root}/deliveries`,
        filters: [f("state", "Stav"), ...filterFields],
        actions: [
          { label: "Detail", read: true },
          { label: "Zkusit doručit znovu", path: "/retry" },
        ],
      },
      {
        title: "Blokované adresy",
        path: `${root}/suppressions`,
        create: {
          label: "Zablokovat adresu",
          fields: [
            required("email", "E-mail", "email"),
            choice(
              "scope",
              "Rozsah blokování",
              ["marketing", "all"],
              "marketing",
            ),
          ],
        },
        remove: true,
      },
      {
        title: "Připomínky",
        path: `${root}/communication/reminder-settings`,
        edit: true,
        fields: [
          f(
            "paymentOffsetsHours",
            "Hodiny vůči splatnosti (např. -24, 24)",
            "numbers",
          ),
          f("preEventHours", "Hodin před akcí", "integer", { value: 24 }),
        ],
      },
    ];
  if (module === "documents")
    return [
      {
        title: "Dokumenty",
        path: `${root}/legal/documents`,
        create: { label: "Nový dokument", fields: documentFields },
        actions: [
          { label: "Verze dokumentu", read: true },
          { label: "Nová verze", path: "/versions", fields: versionFields },
        ],
      },
      {
        title: "Požadavky na potvrzení",
        path: `${root}/legal/requirements`,
        create: {
          label: "Přidat požadavek",
          fields: [
            f("documentId", "Dokument", "text-input", {
              required: true,
              source: `${root}/legal/documents`,
            }),
            choice(
              "type",
              "Typ",
              [
                "terms",
                "privacy_notice",
                "health_declaration",
                "consent",
                "likeness",
              ],
              "consent",
            ),
            ...requirementFields,
          ],
        },
        edit: true,
        fields: requirementFields,
        remove: true,
      },
      { title: "Záznamy potvrzení", path: `${root}/legal/confirmations` },
    ];
  if (module === "payments")
    return [
      {
        title: "Nespárované platby",
        path: `${root}/unmatched-transactions`,
        actions: [
          { label: "Kandidáti", path: "/candidates", read: true },
          {
            label: "Přiřadit platbu",
            path: "/allocate",
            fields: [
              required("orderId", "ID objednávky"),
              ...reason,
              f("paymentRequestId", "ID platebního požadavku"),
            ],
          },
        ],
      },
      {
        title: "Vratky",
        path: `${root}/refund-tasks`,
        create: {
          label: "Nová vratka",
          fields: [
            required("orderId", "ID objednávky"),
            required("amountMinor", "Částka v haléřích", "integer"),
            ...reason,
            f("note", "Poznámka"),
          ],
        },
        actions: [
          {
            label: "Označit za vyřízenou",
            path: "/settle",
            roles: finance,
            fields: [
              choice(
                "method",
                "Způsob vrácení",
                ["bank_transfer", "cash", "other"],
                "bank_transfer",
              ),
              f("reference", "Reference"),
              f("note", "Poznámka"),
            ],
          },
          {
            label: "Zrušit vratku",
            path: "/cancel",
            roles: finance,
            fields: reason,
            danger: true,
          },
        ],
      },
    ];
  if (module === "audit")
    return [
      {
        title: "Auditní záznamy",
        path: `${root}/audit-log`,
        filters: [
          f("eventId", "ID akce"),
          f("action", "Akce"),
          f("page", "Stránka", "integer", { value: 1 }),
          f("perPage", "Na stránku", "integer", { value: 25 }),
        ],
      },
    ];
  return [];
}

export const programFields = [
  required("title", "Název bodu programu"),
  f("description", "Popis", "text"),
  required("startsAt", "Začátek (ISO 8601)"),
  required("endsAt", "Konec (ISO 8601)"),
  f("location", "Místo"),
  f("responsible", "Odpovědná osoba"),
  f("activityId", "ID aktivity"),
  choice("visibility", "Viditelnost", ["public", "participants"], "public"),
  f("important", "Významná změna", "boolean"),
];
export const newsFields = [
  required("title", "Titulek"),
  required("body", "Text", "text"),
  f("priority", "Priorita", "integer", { value: 0 }),
  f("publish", "Publikovat ihned", "boolean"),
  f("broadcast", "Rozeslat e-mailem", "boolean"),
];
export const formFieldFields = [
  required("key", "Stálý klíč pole"),
  choice(
    "type",
    "Typ pole",
    [
      "short_text",
      "long_text",
      "email",
      "phone",
      "date",
      "number",
      "yes_no",
      "single_choice",
      "multi_choice",
      "info_text",
      "legal_requirement",
    ],
    "short_text",
  ),
  required("label", "Popisek"),
  f("help", "Nápověda"),
  f("required", "Povinné", "boolean"),
  choice("scope", "Odpovídá", ["person", "order"], "person"),
  choice("semantic", "Význam", [
    "first_name",
    "last_name",
    "birth_date",
    "email",
    "phone",
    "guardian_first_name",
    "guardian_last_name",
    "guardian_email",
    "guardian_phone",
  ]),
  obj("constraints", "Omezení", [
    f("minLength", "Minimální délka", "integer"),
    f("maxLength", "Maximální délka", "integer"),
    f("min", "Minimum", "integer"),
    f("max", "Maximum", "integer"),
    arr("choices", "Možnosti", [
      required("value", "Hodnota"),
      required("label", "Popisek"),
    ]),
    f("integer", "Pouze celá čísla", "boolean"),
    f("minDate", "Nejdříve", "date"),
    f("maxDate", "Nejpozději", "date"),
    f("minAge", "Minimální věk", "integer"),
    f("maxAge", "Maximální věk", "integer"),
    f("referenceDate", "Referenční datum", "date"),
    f("minChoices", "Nejméně možností", "integer"),
    f("maxChoices", "Nejvíce možností", "integer"),
    f("requirementId", "ID právního požadavku"),
  ]),
  obj("condition", "Podmínka zobrazení (volitelné)", [
    choice("type", "Zobrazit podle", ["activity", "variant", "answer"]),
    f("ids", "ID aktivit nebo variant", "strings"),
    f("field", "Klíč předchozí otázky"),
    choice("operator", "Podmínka odpovědi", [
      "filled",
      "empty",
      "equals",
      "not_equals",
    ]),
    f("value", "Očekávaná hodnota"),
  ]),
  f("activityIds", "Pouze aktivity (ID)", "strings"),
  f("variantIds", "Pouze varianty (ID)", "strings"),
];
const publishActions: Action[] = [
  { label: "Publikovat", path: "/publish" },
  { label: "Stáhnout z publikace", path: "/unpublish" },
  {
    label: "Archivace",
    method: "PUT",
    path: "/archive",
    fields: [f("inArchive", "Zobrazit v archivu", "boolean")],
  },
];
export function eventSpecs(root: string, tab: string): ResourceSpec[] {
  if (tab === "Program")
    return [
      {
        title: "Program",
        path: `${root}/program`,
        fields: programFields,
        create: { label: "Přidat bod", fields: programFields },
        edit: true,
        remove: true,
      },
      {
        title: "Aktuality",
        path: `${root}/news`,
        fields: newsFields,
        create: { label: "Napsat aktualitu", fields: newsFields },
        edit: true,
        actions: [{ label: "Publikovat", path: "/publish" }],
      },
    ];
  if (tab === "Formulář")
    return [
      {
        title: "Registrační formulář a verze",
        path: `${root}/form`,
        actions: [
          {
            label: "Upravit koncept",
            method: "PUT",
            path: "/draft",
            fields: [arr("fields", "Pole formuláře", formFieldFields)],
          },
          { label: "Publikovat novou verzi", path: "/publish" },
        ],
      },
    ];
  if (tab === "Kapacity")
    return [
      {
        title: "Obsazenost",
        path: `${root}/capacity`,
        rowsKey: "buckets",
        create: {
          label: "Změnit kapacitu",
          fields: [
            choice(
              "scope",
              "Úroveň",
              ["event", "activity", "variant"],
              "event",
            ),
            f("scopeId", "ID aktivity nebo varianty"),
            f("limit", "Limit (prázdné = neomezený)", "integer"),
            ...reason,
          ],
        },
      },
    ];
  if (tab === "Obsah")
    return [
      {
        title: "Fotogalerie a externí kolekce",
        path: `${root}/collections`,
        create: {
          label: "Nová kolekce",
          fields: [
            required("title", "Název"),
            choice(
              "visibility",
              "Viditelnost",
              ["public", "link_only", "participants"],
              "public",
            ),
            f("externalUrl", "Externí galerie", "url"),
          ],
        },
        actions: [
          ...publishActions,
          { label: "Fotografie", path: "/photos", read: true },
          {
            label: "Nahrát fotografii",
            path: "/photos",
            multipart: true,
            fields: [
              required("photo", "Fotografie (JPEG, PNG, WebP)", "file"),
              f("description", "Popis"),
              f("author", "Autor"),
              f("eventPart", "Část akce"),
              f("position", "Pořadí", "integer", { value: 0 }),
            ],
          },
        ],
      },
      {
        title: "Materiály",
        path: `${root}/materials`,
        create: {
          label: "Přidat materiál",
          fields: [
            required("title", "Název"),
            required("url", "Odkaz", "url"),
            choice(
              "type",
              "Typ",
              ["results", "video", "document", "partners", "other"],
              "other",
            ),
            f("position", "Pořadí", "integer", { value: 0 }),
          ],
        },
        actions: publishActions,
      },
    ];
  return [];
}
