"use client";
/* eslint-disable @next/next/no-img-element -- Gallery images come directly from the authenticated backend photo routes. */
import { useState } from "react";
import {
  Box,
  ErrorMessage,
  secondary,
  useData,
  type Row,
} from "./workspace-ui";

export function PublicContent({
  root,
  archive = false,
}: {
  root: string;
  archive?: boolean;
}) {
  const result = useData<{ collections: Row[]; materials: Row[] }>(
    `${root}/${archive ? "archive" : "content"}`,
  );
  const [collection, setCollection] = useState("");
  if (result.error) return <ErrorMessage error={result.error} />;
  if (
    !result.data ||
    (!result.data.collections.length && !result.data.materials.length)
  )
    return null;
  return (
    <Box>
      <h2 className="mb-5 text-xl font-semibold">
        {archive ? "Archiv akce" : "Fotografie a materiály"}
      </h2>
      <div className="space-y-3">
        {result.data.collections.map((c) => (
          <div
            key={String(c.id)}
            className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-stone-200 p-4"
          >
            <h3 className="font-medium">{String(c.title)}</h3>
            {typeof c.externalUrl === "string" ? (
              <a
                className={secondary}
                href={c.externalUrl}
                target="_blank"
                rel="noreferrer"
              >
                Otevřít galerii
              </a>
            ) : (
              <button
                className={secondary}
                onClick={() => setCollection(String(c.id))}
              >
                Prohlédnout fotografie ({String(c.photoCount)})
              </button>
            )}
          </div>
        ))}
        {result.data.materials.map((m) => (
          <a
            key={String(m.id)}
            className="block rounded-xl border border-stone-200 p-4 text-lime-900 underline"
            href={String(m.url)}
            target="_blank"
            rel="noreferrer"
          >
            {String(m.title)}
          </a>
        ))}
      </div>
      {collection && (
        <div className="mt-5">
          <PhotoGallery
            path={`${root}/collections/${encodeURIComponent(collection)}/photos`}
          />
        </div>
      )}
    </Box>
  );
}
export function PhotoGallery({ path }: { path: string }) {
  const [offset, setOffset] = useState(0);
  const result = useData<{ items: Row[] }>(
    `${path}${path.includes("?") ? "&" : "?"}limit=60&offset=${offset}`,
  );
  return (
    <div className="space-y-4">
      <ErrorMessage error={result.error} />
      <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
        {result.data?.items.map((photo) => (
          <figure key={String(photo.id)}>
            <a href={String(photo.url)} target="_blank" rel="noreferrer">
              {/* Backend provides same-origin image URLs; no Next image proxy is needed. */}
              <img
                loading="lazy"
                src={String(photo.thumbnailUrl || photo.url)}
                alt={String(
                  photo.author ? `Foto: ${photo.author}` : "Fotografie z akce",
                )}
                className="aspect-square w-full rounded-xl object-cover"
              />
            </a>
            {photo.author != null && (
              <figcaption className="mt-2 text-xs text-stone-500">
                {String(photo.author)}
              </figcaption>
            )}
          </figure>
        ))}
      </div>
      <div className="flex gap-3">
        <button
          disabled={!offset}
          className={secondary}
          onClick={() => setOffset(Math.max(0, offset - 60))}
        >
          Předchozí
        </button>
        <button
          disabled={!result.data || result.data.items.length < 60}
          className={secondary}
          onClick={() => setOffset(offset + 60)}
        >
          Další
        </button>
      </div>
    </div>
  );
}
