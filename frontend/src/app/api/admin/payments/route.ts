
import { adminApi } from "@/lib/admin-api";

export const runtime = "nodejs";

export async function GET(request: Request) {
  const url = new URL(request.url);
  const params = new URLSearchParams();

  for (const key of ["search", "status"]) {
    const value = url.searchParams.get(key);
    if (value) params.set(key, value.slice(0, 100));
  }

  const page = Number(url.searchParams.get("page") ?? "1");

  params.set(
    "page",
    String(Number.isSafeInteger(page) && page > 0 ? page : 1)
  );

  return adminApi(`/admin/payments?${params.toString()}`);
}
