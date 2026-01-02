package com.mandiri.qris.repository;

import com.mandiri.qris.entity.MandiriQrisPayment;
import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.stereotype.Repository;

import java.util.Optional;

@Repository
public interface MandiriQrisPaymentRepository extends JpaRepository<MandiriQrisPayment, Long> {
    
    Optional<MandiriQrisPayment> findByQrId(String qrId);
    
    Optional<MandiriQrisPayment> findByReference(String reference);
    
    boolean existsByReference(String reference);
}
