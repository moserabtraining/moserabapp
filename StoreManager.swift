//
//  StoreManager.swift
//  Moserab
//
//  Created by Von Lemberg Tatjana on 14.04.25.
//
//
//  LoginStoreManager.swift
//  Moserab
//
//  Created by Von Lemberg Tatjana on 14.04.25.
//
import StoreKit

enum PurchaseError: Error {
    case invalidProduct
    case purchaseFailed
    case receiptVerificationFailed
}

class StoreManager: NSObject, ObservableObject, SKProductsRequestDelegate, SKPaymentTransactionObserver {
    static let shared = StoreManager()
 
    
    @Published var products: [SKProduct] = []
    @Published var isPurchasing = false
    @Published var purchaseCompleted = false
    @Published var purchaseError: Error?
    
    private let productIdentifiers = Set(["moserab09"])
    private var productsRequest: SKProductsRequest?
    private var completionHandler: ((Result<Bool, Error>) -> Void)?
    
    override init() {
        super.init()
        SKPaymentQueue.default().add(self)
    }
    
    deinit {
        SKPaymentQueue.default().remove(self)
    }
    
    func loadProducts() {
        productsRequest = SKProductsRequest(productIdentifiers: productIdentifiers)
        productsRequest?.delegate = self
        productsRequest?.start()
    }
    
    func purchase(product: SKProduct, completion: @escaping (Result<Bool, Error>) -> Void) {
        guard SKPaymentQueue.canMakePayments() else {
            completion(.failure(PurchaseError.purchaseFailed))
            return
        }
        
        self.completionHandler = completion
        self.isPurchasing = true
        
        let payment = SKPayment(product: product)
        SKPaymentQueue.default().add(payment)
    }
    
    func verifyReceipt(completion: @escaping (Result<Bool, Error>) -> Void) {
        guard let receiptURL = Bundle.main.appStoreReceiptURL else {
            completion(.failure(PurchaseError.receiptVerificationFailed))
            return
        }
        
        guard FileManager.default.fileExists(atPath: receiptURL.path) else {
            // Refresh receipt if it doesn't exist
            let request = SKReceiptRefreshRequest()
            request.start()
            completion(.failure(PurchaseError.receiptVerificationFailed))
            return
        }
        
        do {
            let receiptData = try Data(contentsOf: receiptURL)
            let receiptBase64 = receiptData.base64EncodedString()
            
            // Send receipt to backend
            NetworkManager.shared.verifyReceipt(receipt: receiptBase64) { result in
                switch result {
                case .success:
                    completion(.success(true))
                case .failure(let error):
                    completion(.failure(error))
                }
            }
        } catch {
            completion(.failure(error))
        }
    }
    
    // MARK: - SKProductsRequestDelegate
    
    func productsRequest(_ request: SKProductsRequest, didReceive response: SKProductsResponse) {
        DispatchQueue.main.async {
            self.products = response.products
            if !response.invalidProductIdentifiers.isEmpty {
                print("Invalid product identifiers: \(response.invalidProductIdentifiers)")
            }
        }
    }
    
    // MARK: - SKPaymentTransactionObserver
    
    func paymentQueue(_ queue: SKPaymentQueue, updatedTransactions transactions: [SKPaymentTransaction]) {
        for transaction in transactions {
            switch transaction.transactionState {
            case .purchased:
                handlePurchased(transaction)
            case .failed:
                handleFailed(transaction)
            case .restored:
                handleRestored(transaction)
            case .deferred, .purchasing:
                break
            @unknown default:
                break
            }
        }
    }
    
    private func handlePurchased(_ transaction: SKPaymentTransaction) {
        verifyReceipt { result in
            DispatchQueue.main.async {
                self.isPurchasing = false
                switch result {
                case .success:
                    self.purchaseCompleted = true
                    self.completionHandler?(.success(true))
                case .failure(let error):
                    self.purchaseError = error
                    self.completionHandler?(.failure(error))
                }
                SKPaymentQueue.default().finishTransaction(transaction)
            }
        }
    }
    
    private func handleFailed(_ transaction: SKPaymentTransaction) {
        DispatchQueue.main.async {
            self.isPurchasing = false
            if let error = transaction.error {
                self.purchaseError = error
                self.completionHandler?(.failure(error))
            } else {
                self.completionHandler?(.failure(PurchaseError.purchaseFailed))
            }
            SKPaymentQueue.default().finishTransaction(transaction)
        }
    }
    
    private func handleRestored(_ transaction: SKPaymentTransaction) {
        verifyReceipt { result in
            DispatchQueue.main.async {
                self.isPurchasing = false
                switch result {
                case .success:
                    self.purchaseCompleted = true
                    self.completionHandler?(.success(true))
                case .failure(let error):
                    self.purchaseError = error
                    self.completionHandler?(.failure(error))
                }
                SKPaymentQueue.default().finishTransaction(transaction)
            }
        }
    }
}


