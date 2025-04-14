//
//  NetworkManager.swift
//  Moserab
//
//  Created by Von Lemberg Tatjana on 14.04.25.
//
import Foundation

enum NetworkError: Error {
    case invalidURL
    case noData
    case serverError(String)
}

class NetworkManager {
    static let shared = NetworkManager()
    
    private let baseURL = "https://your-backend-url.com/api"
    private let session = URLSession.shared
    
    private init() {}
    
    func verifyReceipt(receipt: String, completion: @escaping (Result<Bool, Error>) -> Void) {
        guard let url = URL(string: "\(baseURL)/verify_receipt.php") else {
            completion(.failure(NetworkError.invalidURL))
            return
        }
        
        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.addValue("application/json", forHTTPHeaderField: "Content-Type")
        
        let userId = UserDefaults.standard.string(forKey: "userId") ?? ""
        
        let body: [String: Any] = [
            "receipt_data": receipt,
            "user_id": userId
        ]
        
        do {
            request.httpBody = try JSONSerialization.data(withJSONObject: body)
        } catch {
            completion(.failure(error))
            return
        }
        
        let task = session.dataTask(with: request) { data, response, error in
            if let error = error {
                completion(.failure(error))
                return
            }
            
            guard let data = data else {
                completion(.failure(NetworkError.noData))
                return
            }
            
            do {
                if let json = try JSONSerialization.jsonObject(with: data) as? [String: Any],
                   let status = json["status"] as? Bool {
                    if status {
                        completion(.success(true))
                    } else if let message = json["message"] as? String {
                        completion(.failure(NetworkError.serverError(message)))
                    } else {
                        completion(.failure(NetworkError.serverError("Unknown error")))
                    }
                } else {
                    completion(.failure(NetworkError.serverError("Invalid response")))
                }
            } catch {
                completion(.failure(error))
            }
        }
        
        task.resume()
    }
    
    func checkPremiumStatus(completion: @escaping (Result<Bool, Error>) -> Void) {
        guard let url = URL(string: "\(baseURL)/user_service.php?action=check_premium") else {
            completion(.failure(NetworkError.invalidURL))
            return
        }
        
        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.addValue("application/json", forHTTPHeaderField: "Content-Type")
        
        let userId = UserDefaults.standard.string(forKey: "userId") ?? ""
        
        let body: [String: Any] = [
            "user_id": userId
        ]
        
        do {
            request.httpBody = try JSONSerialization.data(withJSONObject: body)
        } catch {
            completion(.failure(error))
            return
        }
        
        let task = session.dataTask(with: request) { data, response, error in
            if let error = error {
                completion(.failure(error))
                return
            }
            
            guard let data = data else {
                completion(.failure(NetworkError.noData))
                return
            }
            
            do {
                if let json = try JSONSerialization.jsonObject(with: data) as? [String: Any],
                   let isPremium = json["is_premium"] as? Bool {
                    completion(.success(isPremium))
                } else {
                    completion(.failure(NetworkError.serverError("Invalid response")))
                }
            } catch {
                completion(.failure(error))
            }
        }
        
        task.resume()
    }
}
