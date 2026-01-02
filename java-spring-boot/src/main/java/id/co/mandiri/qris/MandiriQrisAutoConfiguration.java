package id.co.mandiri.qris;

import org.springframework.boot.autoconfigure.condition.ConditionalOnProperty;
import org.springframework.boot.context.properties.EnableConfigurationProperties;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;

@Configuration
@EnableConfigurationProperties(MandiriQrisProperties.class)
@ConditionalOnProperty(prefix = "mandiri.qris", name = "enabled", havingValue = "true", matchIfMissing = true)
public class MandiriQrisAutoConfiguration {

    @Bean
    public MandiriQrisService mandiriQrisService(MandiriQrisProperties properties) {
        return new MandiriQrisService(properties);
    }
}
