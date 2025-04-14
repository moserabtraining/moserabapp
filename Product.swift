//
//  Product.swift
//  Moserab
//
//  Created by Von Lemberg Tatjana on 14.04.25.
//
// ios-app/Models/Product.swift

import Foundation
import StoreKit

struct Product {
    let id: String
    let name: String
    let description: String
    let price: Decimal
    let priceFormatted: String
    let skProduct: SKProduct
    
    init(from skProduct: SKProduct) {
        self.id = skProduct.productIdentifier
        self.name = skProduct.localizedTitle
        self.description = skProduct.localizedDescription
        self.price = skProduct.price as Decimal
        
        let formatter = NumberFormatter()
        formatter.numberStyle = .currency
        formatter.locale = skProduct.priceLocale
        self.priceFormatted = formatter.string(from: skProduct.price) ?? "\(skProduct.price)"
        
        self.skProduct = skProduct
    }
}
