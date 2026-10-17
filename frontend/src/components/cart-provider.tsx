
"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useState,
  type ReactNode,
} from "react";

export type CartProduct = {
  id: number;
  name: string;
  sku: string;
  price: string | number;
  inventory?: { quantity: number } | null;
};

export type CartItem = {
  product: CartProduct;
  quantity: number;
};

type CartContextType = {
  items: CartItem[];
  addItem: (product: CartProduct) => void;
  updateQuantity: (id: number, quantity: number) => void;
  removeItem: (id: number) => void;
  clearCart: () => void;
  count: number;
  subtotal: number;
};

const STORAGE_KEY = "shopsphere-cart-v1";

const CartContext = createContext<CartContextType | null>(null);

export function CartProvider({ children }: { children: ReactNode }) {
  const [items, setItems] = useState<CartItem[]>([]);
  const [ready, setReady] = useState(false);

  useEffect(() => {
    try {
      const stored = localStorage.getItem(STORAGE_KEY);
      if (stored) {
        const parsed: unknown = JSON.parse(stored);

        if (Array.isArray(parsed)) {
          setItems(
            parsed.filter(
              (item): item is CartItem =>
                typeof item === "object" &&
                item !== null &&
                typeof item.product?.id === "number" &&
                typeof item.product?.name === "string" &&
                Number.isInteger(item.quantity) &&
                item.quantity > 0
            )
          );
        }
      }
    } catch {
      localStorage.removeItem(STORAGE_KEY);
    }

    setReady(true);
  }, []);

  useEffect(() => {
    if (ready) {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
    }
  }, [items, ready]);

  const addItem = useCallback((product: CartProduct) => {
    setItems((current) => {
      const existing = current.find(
        (item) => item.product.id === product.id
      );

      const stock = Math.max(0, product.inventory?.quantity ?? 0);

      if (stock === 0) return current;

      if (existing) {
        return current.map((item) =>
          item.product.id === product.id
            ? {
                product,
                quantity: Math.min(item.quantity + 1, stock),
              }
            : item
        );
      }

      return [...current, { product, quantity: 1 }];
    });
  }, []);

  const updateQuantity = useCallback(
    (id: number, quantity: number) => {
      setItems((current) =>
        current.map((item) => {
          if (item.product.id !== id) return item;

          const stock = item.product.inventory?.quantity ?? 0;

          return {
            ...item,
            quantity: Math.max(
              1,
              Math.min(Math.floor(quantity), stock || 1)
            ),
          };
        })
      );
    },
    []
  );

  const removeItem = useCallback((id: number) => {
    setItems((current) =>
      current.filter((item) => item.product.id !== id)
    );
  }, []);

  const clearCart = useCallback(() => setItems([]), []);

  const count = items.reduce(
    (sum, item) => sum + item.quantity,
    0
  );

  const subtotal = items.reduce(
    (sum, item) =>
      sum + Number(item.product.price) * item.quantity,
    0
  );

  return (
    <CartContext.Provider
      value={{
        items,
        addItem,
        updateQuantity,
        removeItem,
        clearCart,
        count,
        subtotal,
      }}
    >
      {children}
    </CartContext.Provider>
  );
}

export function useCart() {
  const context = useContext(CartContext);

  if (!context) {
    throw new Error("useCart must be used inside CartProvider");
  }

  return context;
}
