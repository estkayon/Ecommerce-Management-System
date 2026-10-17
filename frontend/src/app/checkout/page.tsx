
"use client";

import Link from "next/link";
import { useEffect, useRef, useState, type FormEvent } from "react";
import { motion, useReducedMotion } from "motion/react";
import { useCart } from "@/components/cart-provider";

type CheckoutResponse = {
  message?: string;
  data?: {
    id: number;
    order_number?: string;
  };
  payment_access_token?: string;
  errors?: Record<string, string[]>;
};

type PaymentResponse = {
  message?: string;
  data?: {
    gateway_url?: string;
    payment_id?: number;
    transaction_id?: string;
  };
  errors?: Record<string, string[]>;
};

type Customer = {
  customer_name: string;
  customer_email: string;
  customer_phone: string;
  shipping_address: string;
};

type CreatedOrder = {
  id: number;
  token: string;
  orderNumber?: string;
};

function formatPrice(value: number) {
  return new Intl.NumberFormat("en-BD", {
    style: "currency",
    currency: "BDT",
    maximumFractionDigits: 2,
  }).format(value);
}

function getErrorMessage(
  response: {
    message?: string;
    errors?: Record<string, string[]>;
  },
  fallback: string
): string {
  const firstError = Object.values(response.errors ?? {})
    .flat()
    .find(Boolean);

  return firstError ?? response.message ?? fallback;
}

export default function CheckoutPage() {
  const { items, subtotal } = useCart();
  const reducedMotion = useReducedMotion();

  const processingRef = useRef(false);

  const [hydrated, setHydrated] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState("");
  const [orderCreated, setOrderCreated] = useState(false);
  const [createdOrder, setCreatedOrder] =
    useState<CreatedOrder | null>(null);

  const [paymentUncertain, setPaymentUncertain] =
    useState(false);

  const [customer, setCustomer] = useState<Customer>({
    customer_name: "",
    customer_email: "",
    customer_phone: "",
    shipping_address: "",
  });

  useEffect(() => {
    setHydrated(true);
  }, []);

  function updateField(
    field: keyof Customer,
    value: string
  ) {
    setCustomer((current) => ({
      ...current,
      [field]: value,
    }));
  }

  async function initiatePayment(order: CreatedOrder) {
    let response: Response;

    try {
      response = await fetch("/api/payments/initiate", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        body: JSON.stringify({
          order_id: order.id,
          payment_access_token: order.token,
        }),
      });
    } catch {
      setPaymentUncertain(true);

      throw new Error(
        `Order #${order.id} was created, but the payment request could not be confirmed. Do not retry until its status is checked.`
      );
    }

    let result: PaymentResponse;

    try {
      result = await response.json();
    } catch {
      setPaymentUncertain(true);

      throw new Error(
        `Order #${order.id} was created, but the payment response was unreadable. Please check its status before retrying.`
      );
    }

    if (response.status >= 500) {
      setPaymentUncertain(true);

      throw new Error(
        `Order #${order.id}: Payment service returned an uncertain result. Please check payment status before retrying.`
      );
    }

    if (!response.ok) {
      throw new Error(
        `Order #${order.id} was created, but payment could not start. ` +
          getErrorMessage(
            result,
            "Please contact support before retrying."
          )
      );
    }

    const gatewayUrl = result.data?.gateway_url;

    if (!gatewayUrl) {
      setPaymentUncertain(true);

      throw new Error(
        `Order #${order.id} was created, but the gateway URL was missing. Check its payment status before retrying.`
      );
    }

    let url: URL;

    try {
      url = new URL(gatewayUrl);
    } catch {
      setPaymentUncertain(true);
      throw new Error("Payment gateway returned an invalid URL.");
    }

    if (url.protocol !== "https:") {
      setPaymentUncertain(true);

      throw new Error(
        "The payment gateway URL is not secure. Redirect cancelled."
      );
    }

    sessionStorage.setItem(
        "shopsphere-pending-payment",
        JSON.stringify({
            orderId: order.id,
            token: order.token,
        })
        );

    window.location.assign(url.toString());
  }

  async function handleCheckout(
    event: FormEvent<HTMLFormElement>
  ) {
    event.preventDefault();

    if (
      processingRef.current ||
      orderCreated ||
      items.length === 0
    ) {
      return;
    }

    processingRef.current = true;
    setSubmitting(true);
    setError("");

    try {
      const checkoutResponse = await fetch("/api/checkout", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        body: JSON.stringify({
          customer_name: customer.customer_name.trim(),
          customer_email:
            customer.customer_email.trim() || null,
          customer_phone: customer.customer_phone.trim(),
          shipping_address:
            customer.shipping_address.trim(),
          items: items.map(({ product, quantity }) => ({
            product_id: product.id,
            quantity,
          })),
        }),
      });

      const checkoutResult: CheckoutResponse =
        await checkoutResponse.json();

      if (!checkoutResponse.ok) {
        throw new Error(
          getErrorMessage(
            checkoutResult,
            "Unable to place your order."
          )
        );
      }

      const orderId = checkoutResult.data?.id;
      const paymentToken =
        checkoutResult.payment_access_token;

      if (
        !orderId ||
        !paymentToken ||
        paymentToken.length !== 64
      ) {
        setOrderCreated(true);
        setPaymentUncertain(true);

        throw new Error(
          "Order creation response was incomplete. Do not submit another order until its status is checked."
        );
      }

      const order: CreatedOrder = {
        id: orderId,
        token: paymentToken,
        orderNumber:
          checkoutResult.data?.order_number,
      };

      setCreatedOrder(order);
      setOrderCreated(true);

      await initiatePayment(order);
    } catch (caught) {
      setError(
        caught instanceof Error
          ? caught.message
          : "Something went wrong. Please try again."
      );
    } finally {
      processingRef.current = false;
      setSubmitting(false);
    }
  }

  async function retryPayment() {
    if (
      !createdOrder ||
      processingRef.current ||
      paymentUncertain
    ) {
      return;
    }

    processingRef.current = true;
    setSubmitting(true);
    setError("");

    try {
      await initiatePayment(createdOrder);
    } catch (caught) {
      setError(
        caught instanceof Error
          ? caught.message
          : "Payment initiation failed."
      );
    } finally {
      processingRef.current = false;
      setSubmitting(false);
    }
  }

  if (!hydrated) {
    return (
      <main className="flex min-h-screen items-center justify-center bg-slate-50">
        <p className="text-sm font-medium text-slate-500">
          Preparing checkout...
        </p>
      </main>
    );
  }

  return (
    <main className="min-h-screen bg-[#f8fafc] text-slate-900">
      <header className="border-b border-slate-200 bg-white">
        <div className="mx-auto flex max-w-6xl items-center justify-between px-5 py-6">
          <Link
            href="/"
            className="text-xl font-black tracking-tight"
          >
            Shop
            <span className="text-indigo-600">
              Sphere.
            </span>
          </Link>

          <span className="rounded-full bg-indigo-50 px-4 py-2 text-xs font-bold text-indigo-700">
            Secure Checkout
          </span>
        </div>
      </header>

      <div className="mx-auto max-w-6xl px-5 py-12 sm:py-16">
        <Link
          href="/cart"
          className="text-sm font-semibold text-indigo-600 hover:underline"
        >
          ← Back to Cart
        </Link>

        <div className="mt-7">
          <span className="text-xs font-bold tracking-[0.2em] text-indigo-600 uppercase">
            Almost There
          </span>

          <h1 className="mt-3 text-4xl font-black tracking-tight sm:text-5xl">
            Complete your order.
          </h1>

          <p className="mt-4 text-slate-500">
            Enter your details to continue to SSLCommerz payment.
          </p>
        </div>

        {items.length === 0 && !orderCreated ? (
          <div className="mt-10 rounded-3xl border border-slate-200 bg-white p-12 text-center">
            <h2 className="text-xl font-bold">
              Your shopping cart is empty
            </h2>

            <Link
              href="/"
              className="mt-6 inline-block rounded-xl bg-indigo-600 px-6 py-3 font-bold text-white"
            >
              Explore Products
            </Link>
          </div>
        ) : (
          <div className="mt-10 grid gap-8 lg:grid-cols-[1fr_380px]">
            <motion.form
              onSubmit={handleCheckout}
              initial={
                reducedMotion
                  ? false
                  : { opacity: 0, y: 20 }
              }
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.5 }}
              className="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm sm:p-10"
            >
              <div className="mb-8">
                <h2 className="text-xl font-extrabold">
                  Customer Information
                </h2>

                <p className="mt-2 text-sm text-slate-500">
                  Your order will use the details below.
                </p>
              </div>

              <div className="grid gap-6 sm:grid-cols-2">
                <label className="block sm:col-span-2">
                  <span className="mb-2 block text-sm font-semibold">
                    Full Name *
                  </span>

                  <input
                    required
                    maxLength={255}
                    autoComplete="name"
                    disabled={orderCreated}
                    value={customer.customer_name}
                    onChange={(event) =>
                      updateField(
                        "customer_name",
                        event.target.value
                      )
                    }
                    placeholder="Enter your full name"
                    className="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3.5 text-sm outline-none transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-50 disabled:opacity-70"
                  />
                </label>

                <label className="block">
                  <span className="mb-2 block text-sm font-semibold">
                    Phone Number *
                  </span>

                  <input
                    required
                    type="tel"
                    maxLength={30}
                    autoComplete="tel"
                    disabled={orderCreated}
                    value={customer.customer_phone}
                    onChange={(event) =>
                      updateField(
                        "customer_phone",
                        event.target.value
                      )
                    }
                    placeholder="01XXXXXXXXX"
                    className="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3.5 text-sm outline-none transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-50 disabled:opacity-70"
                  />
                </label>

                <label className="block">
                  <span className="mb-2 block text-sm font-semibold">
                    Email Address
                  </span>

                  <input
                    type="email"
                    maxLength={255}
                    autoComplete="email"
                    disabled={orderCreated}
                    value={customer.customer_email}
                    onChange={(event) =>
                      updateField(
                        "customer_email",
                        event.target.value
                      )
                    }
                    placeholder="you@example.com"
                    className="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3.5 text-sm outline-none transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-50 disabled:opacity-70"
                  />
                </label>

                <label className="block sm:col-span-2">
                  <span className="mb-2 block text-sm font-semibold">
                    Shipping Address *
                  </span>

                  <textarea
                    required
                    rows={4}
                    maxLength={1000}
                    autoComplete="street-address"
                    disabled={orderCreated}
                    value={customer.shipping_address}
                    onChange={(event) =>
                      updateField(
                        "shipping_address",
                        event.target.value
                      )
                    }
                    placeholder="House, road, area, city..."
                    className="w-full resize-none rounded-xl border border-slate-200 bg-slate-50 px-4 py-3.5 text-sm outline-none transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-50 disabled:opacity-70"
                  />
                </label>
              </div>

              {error && (
                <div
                  role="alert"
                  className="mt-7 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm leading-6 text-rose-700"
                >
                  {error}
                </div>
              )}

              {orderCreated && (
                <div className="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-5">
                  <p className="font-bold text-amber-900">
                    Order Already Created
                  </p>

                  <p className="mt-2 text-sm leading-6 text-amber-800">
                    {createdOrder
                      ? `Order #${createdOrder.id} has been created.`
                      : "Your order may have been created."}
                    {" "}Do not create another order for this purchase.
                  </p>

                  {paymentUncertain && (
                    <p className="mt-3 text-sm leading-6 text-amber-800">
                      Payment status is uncertain. Please contact
                      the store administrator to confirm its
                      status before trying again.
                    </p>
                  )}

                  {createdOrder && !paymentUncertain && (
                    <button
                      type="button"
                      disabled={submitting}
                      onClick={retryPayment}
                      className="mt-4 w-full rounded-xl border border-indigo-200 bg-white px-6 py-3.5 text-sm font-bold text-indigo-700 transition hover:bg-indigo-50 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                      {submitting
                        ? "Checking Payment..."
                        : "Retry Payment for Existing Order"}
                    </button>
                  )}
                </div>
              )}

              <motion.button
                type="submit"
                disabled={submitting || orderCreated}
                whileHover={
                  reducedMotion || submitting
                    ? undefined
                    : { y: -2 }
                }
                whileTap={
                  reducedMotion || submitting
                    ? undefined
                    : { scale: 0.98 }
                }
                className="mt-8 flex w-full items-center justify-center gap-3 rounded-xl bg-indigo-600 px-6 py-4 text-sm font-bold text-white shadow-lg shadow-indigo-100 transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
              >
                {submitting
                  ? "Processing Your Order..."
                  : orderCreated
                    ? "Order Created"
                    : "Continue to Secure Payment"}

                {!submitting && !orderCreated && (
                  <span>→</span>
                )}
              </motion.button>

              <p className="mt-5 text-center text-xs leading-5 text-slate-400">
                Final prices, shipping fees and stock
                availability are validated by the server.
              </p>
            </motion.form>

            <motion.aside
              initial={
                reducedMotion
                  ? false
                  : { opacity: 0, y: 20 }
              }
              animate={{ opacity: 1, y: 0 }}
              transition={{
                duration: 0.5,
                delay: 0.1,
              }}
              className="h-fit rounded-3xl border border-slate-200 bg-white p-7 shadow-sm"
            >
              <h2 className="text-xl font-extrabold">
                Order Summary
              </h2>

              <div className="mt-6 space-y-5">
                {items.map(({ product, quantity }) => (
                  <div
                    key={product.id}
                    className="flex justify-between gap-5 border-b border-slate-100 pb-4"
                  >
                    <div>
                      <p className="text-sm font-semibold">
                        {product.name}
                      </p>

                      <p className="mt-1 text-xs text-slate-400">
                        Qty: {quantity}
                      </p>
                    </div>

                    <p className="whitespace-nowrap text-sm font-bold">
                      {formatPrice(
                        Number(product.price) * quantity
                      )}
                    </p>
                  </div>
                ))}
              </div>

              <div className="mt-6 flex justify-between text-sm">
                <span className="text-slate-500">
                  Estimated Subtotal
                </span>

                <span className="font-bold">
                  {formatPrice(subtotal)}
                </span>
              </div>

              <div className="mt-4 flex justify-between text-sm">
                <span className="text-slate-500">
                  Shipping
                </span>

                <span className="font-medium">
                  Calculated by server
                </span>
              </div>

              <div className="mt-6 border-t border-slate-200 pt-6">
                <p className="text-xs leading-6 text-slate-500">
                  You will be redirected to SSLCommerz to
                  complete your payment securely.
                </p>
              </div>
            </motion.aside>
          </div>
        )}
      </div>
    </main>
  );
}
