//
//  LoginViewController.swift
//  Moserab
//
//  Created by Von Lemberg Tatjana on 14.04.25.
//

import UIKit
import StoreKit

class LoginViewController: UIViewController {
    
    private let storeManager = StoreManager.shared
    private var products: [Product] = []
    
    private let purchaseButton: UIButton = {
        let button = UIButton(type: .system)
        button.setTitle("Premium Abonnement kaufen", for: .normal)
        button.backgroundColor = .systemBlue
        button.setTitleColor(.white, for: .normal)
        button.layer.cornerRadius = 8
        button.translatesAutoresizingMaskIntoConstraints = false
        return button
    }()
    
    private let statusLabel: UILabel = {
        let label = UILabel()
        label.textAlignment = .center
        label.text = "Status wird geladen..."
        label.translatesAutoresizingMaskIntoConstraints = false
        return label
    }()
    
    override func viewDidLoad() {
        super.viewDidLoad()
        setupUI()
        loadProducts()
        checkPremiumStatus()
    }
    
    private func setupUI() {
        view.backgroundColor = .white
        
        view.addSubview(purchaseButton)
        view.addSubview(statusLabel)
        
        NSLayoutConstraint.activate([
            purchaseButton.centerXAnchor.constraint(equalTo: view.centerXAnchor),
            purchaseButton.centerYAnchor.constraint(equalTo: view.centerYAnchor),
            purchaseButton.widthAnchor.constraint(equalToConstant: 250),
            purchaseButton.heightAnchor.constraint(equalToConstant: 50),
            
            statusLabel.centerXAnchor.constraint(equalTo: view.centerXAnchor),
            statusLabel.topAnchor.constraint(equalTo: purchaseButton.bottomAnchor, constant: 20),
            statusLabel.widthAnchor.constraint(equalTo: view.widthAnchor, multiplier: 0.8)
        ])
        
        purchaseButton.addTarget(self, action: #selector(purchaseTapped), for: .touchUpInside)
    }
    
    private func loadProducts() {
        storeManager.loadProducts()
        
        // Listen for product updates
        NotificationCenter.default.addObserver(forName: NSNotification.Name(rawValue: "ProductsLoaded"), object: nil, queue: nil) { [weak self] _ in
            self?.updateProductsList()
        }
    }
    
    private func updateProductsList() {
        self.products = storeManager.products.map { Product(from: $0) }
        
        if self.products.isEmpty {
            self.purchaseButton.isEnabled = false
            self.purchaseButton.setTitle("Keine Produkte verfügbar", for: .normal)
        } else {
            self.purchaseButton.isEnabled = true
            self.purchaseButton.setTitle("Premium für \(self.products.first?.priceFormatted ?? "") / Monat", for: .normal)
        }
    }
    
    @objc private func purchaseTapped() {
        guard let product = self.products.first?.skProduct else {
            showAlert(title: "Fehler", message: "Produkt nicht verfügbar")
            return
        }
        
        statusLabel.text = "Kauf wird verarbeitet..."
        
        storeManager.purchase(product: product) { [weak self] result in
            DispatchQueue.main.async {
                switch result {
                case .success:
                    self?.statusLabel.text = "Premium-Zugang aktiviert!"
                    self?.showAlert(title: "Erfolg", message: "Dein Premium-Zugang wurde aktiviert.")
                case .failure(let error):
                    self?.statusLabel.text = "Fehler beim Kauf"
                    self?.showAlert(title: "Fehler", message: "Beim Kauf ist ein Fehler aufgetreten: \(error.localizedDescription)")
                }
            }
        }
    }
    
    private func checkPremiumStatus() {
        NetworkManager.shared.checkPremiumStatus { [weak self] result in
            DispatchQueue.main.async {
                switch result {
                case .success(let isPremium):
                    if isPremium {
                        self?.statusLabel.text = "Du hast Premium-Zugang"
                        self?.purchaseButton.isEnabled = false
                        self?.purchaseButton.setTitle("Premium bereits aktiv", for: .normal)
                    } else {
                        self?.statusLabel.text = "Kein Premium-Zugang"
                    }
                case .failure:
                    self?.statusLabel.text = "Status konnte nicht abgerufen werden"
                }
            }
        }
    }
    
    private func showAlert(title: String, message: String) {
        let alert = UIAlertController(title: title, message: message, preferredStyle: .alert)
        alert.addAction(UIAlertAction(title: "OK", style: .default))
        present(alert, animated: true)
    }
}
