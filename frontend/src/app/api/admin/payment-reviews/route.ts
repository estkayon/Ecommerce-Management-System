
import { adminApi } from "@/lib/admin-api";

export const runtime = "nodejs";

export async function GET(request: Request) {
  const url = new URL(request.url);
  const page = Number(url.searchParams.get("page") ?? "1");

  const safePage =
    Number.isSafeInteger(page) && page > 0 ? page : 1;

  return adminApi(`/admin/payment-reviews?page=${safePage}`);
}
