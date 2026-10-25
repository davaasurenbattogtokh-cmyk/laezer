declare namespace Cloudflare {
  interface Env {
    DB?: D1Database;
    BUCKET?: R2Bucket;
  }
}

// Future server-only payment configuration; values are supplied through runtime secrets.
declare namespace Cloudflare {
  interface Env {
    QPAY_ENABLED?: string;
    QPAY_ENVIRONMENT?: string;
    QPAY_CLIENT_ID?: string;
    QPAY_CLIENT_SECRET?: string;
    QPAY_INVOICE_CODE?: string;
    QPAY_PUBLIC_ORIGIN?: string;
    QPAY_CALLBACK_SECRET?: string;
  }
}

declare namespace Cloudflare {
  interface Env {
    ADMIN_LOGIN_NAME?: string;
    ADMIN_PASSWORD_HASH?: string;
  }
}
