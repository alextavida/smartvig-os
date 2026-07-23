package com.smartvigos;

import com.facebook.react.modules.network.OkHttpClientFactory;
import com.facebook.react.modules.network.OkHttpClientProvider;
import okhttp3.OkHttpClient;

import javax.net.ssl.SSLContext;
import javax.net.ssl.TrustManager;
import javax.net.ssl.X509TrustManager;
import java.security.cert.X509Certificate;

/**
 * Aceita certificados autoassinados do servidor XAMPP corporativo.
 * Necessario porque XAMPP nao usa CA reconhecida pelo Android.
 */
public class CustomClientFactory implements OkHttpClientFactory {

    private static final X509TrustManager TRUST_ALL = new X509TrustManager() {
        @Override public void checkClientTrusted(X509Certificate[] c, String a) {}
        @Override public void checkServerTrusted(X509Certificate[] c, String a) {}
        @Override public X509Certificate[] getAcceptedIssuers() { return new X509Certificate[0]; }
    };

    @Override
    public OkHttpClient createNewNetworkModuleClient() {
        try {
            SSLContext ctx = SSLContext.getInstance("TLS");
            ctx.init(null, new TrustManager[]{TRUST_ALL}, null);

            return OkHttpClientProvider.createClientBuilder()
                    .sslSocketFactory(ctx.getSocketFactory(), TRUST_ALL)
                    .hostnameVerifier((hostname, session) -> true)
                    .build();
        } catch (Exception e) {
            throw new RuntimeException("Falha na configuracao SSL", e);
        }
    }
}
