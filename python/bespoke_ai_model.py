#!/usr/bin/env python3
"""
Bespoke AI Model for Trivia Game Learning
This model learns from player answers and question contexts to improve performance over time.
"""

import json
import sys
import mysql.connector
import numpy as np
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.ensemble import RandomForestRegressor
from sklearn.metrics.pairwise import cosine_similarity
from datetime import datetime
import pickle
import os
import logging
from typing import Dict, List, Tuple, Optional
import argparse

# Configure logging
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')
logger = logging.getLogger(__name__)

class BespokeAIModel:
    def __init__(self, db_config: Dict, model_id: int):
        self.db_config = db_config
        self.model_id = model_id
        self.vectorizer = TfidfVectorizer(max_features=1000, stop_words='english')
        self.score_predictor = RandomForestRegressor(n_estimators=100, random_state=42)
        self.model_trained = False
        self.model_path = f"models/bespoke_ai_model_{model_id}.pkl"
        
        # Create models directory if it doesn't exist
        os.makedirs("models", exist_ok=True)
        
        # Try to load existing model
        self.load_model()
    
    def get_db_connection(self):
        """Create database connection"""
        return mysql.connector.connect(**self.db_config)
    
    def load_training_data(self) -> List[Dict]:
        """Load training data from database"""
        conn = self.get_db_connection()
        cursor = conn.cursor(dictionary=True)
        
        query = """
        SELECT 
            td.*,
            gq.question, gq.answer as correct_answer, gq.score_awarded,
            gtd.name as difficulty_name,
            gtc.name as category_name
        FROM bespoke_ai_training_data td
        LEFT JOIN game_questions gq ON td.question_id = gq.id
        LEFT JOIN game_type_difficulties gtd ON td.difficulty_id = gtd.id
        LEFT JOIN game_type_categories gtc ON td.category_id = gtc.id
        WHERE td.model_id = %s
        ORDER BY td.created_at ASC
        """
        
        cursor.execute(query, (self.model_id,))
        data = cursor.fetchall()
        
        cursor.close()
        conn.close()
        
        logger.info(f"Loaded {len(data)} training samples for model {self.model_id}")
        return data
    
    def prepare_features(self, question: str, player_answer: str = "", difficulty: str = "", category: str = "") -> np.ndarray:
        """Prepare features for the ML model"""
        # Combine text features
        text_features = f"{question} {player_answer} {difficulty} {category}"
        
        # If vectorizer hasn't been fitted, fit it
        if not hasattr(self.vectorizer, 'vocabulary_'):
            # Use dummy data to fit if needed
            self.vectorizer.fit([text_features])
        
        # Transform to TF-IDF features
        tfidf_features = self.vectorizer.transform([text_features]).toarray()[0]
        
        # Add additional features
        additional_features = [
            len(question.split()),  # Question length
            len(player_answer.split()) if player_answer else 0,  # Player answer length
            1 if difficulty.lower() == 'easy' else 2 if difficulty.lower() == 'medium' else 3,  # Difficulty encoding
            hash(category) % 100 if category else 0,  # Category hash
        ]
        
        # Combine all features
        features = np.concatenate([tfidf_features, additional_features])
        return features
    
    def train_model(self):
        """Train the model on existing data"""
        training_data = self.load_training_data()
        
        if len(training_data) < 10:  # Need minimum data to train
            logger.warning(f"Insufficient training data for model {self.model_id}: {len(training_data)} samples")
            return False
        
        # Prepare training features and targets
        X = []
        y = []
        
        # First pass: fit vectorizer on all text data
        all_texts = []
        for record in training_data:
            text = f"{record['question_text']} {record.get('player_answer', '')} {record.get('difficulty_id', '')} {record.get('category_id', '')}"
            all_texts.append(text)
        
        self.vectorizer.fit(all_texts)
        
        # Second pass: prepare features and targets
        for record in training_data:
            features = self.prepare_features(
                record['question_text'],
                record.get('player_answer', ''),
                str(record.get('difficulty_id', '')),
                str(record.get('category_id', ''))
            )
            X.append(features)
            
            # Target: score efficiency (score achieved / max possible score)
            efficiency = record['score_achieved'] / max(record['max_possible_score'], 1)
            y.append(efficiency)
        
        X = np.array(X)
        y = np.array(y)
        
        # Train the model
        self.score_predictor.fit(X, y)
        self.model_trained = True
        
        # Save the model
        self.save_model()
        
        logger.info(f"Model {self.model_id} trained on {len(X)} samples")
        return True
    
    def predict_answer(self, question: str, player_answer: str = "", difficulty: str = "", category: str = "", max_score: int = 1) -> Tuple[str, float]:
        """Predict the best answer for a question"""
        try:
            # If model isn't trained, train it first
            if not self.model_trained:
                self.train_model()
            
            # Get similar questions from training data for context
            similar_answers = self.get_similar_question_answers(question, difficulty, category)
            
            # Generate base answer using pattern matching and learning
            base_answer = self.generate_base_answer(question, similar_answers, player_answer)
            
            # If we have a trained model, predict score and refine answer
            if self.model_trained:
                features = self.prepare_features(question, player_answer, difficulty, category)
                predicted_efficiency = self.score_predictor.predict([features])[0]
                predicted_score = predicted_efficiency * max_score
                
                # Refine answer based on predicted performance
                refined_answer = self.refine_answer(base_answer, similar_answers, predicted_efficiency)
                
                logger.info(f"Predicted answer for question: {refined_answer} (predicted score: {predicted_score:.2f})")
                return refined_answer, predicted_score
            
            # Fallback if model not trained
            logger.info(f"Base answer for question: {base_answer}")
            return base_answer, max_score * 0.5  # Conservative estimate
            
        except Exception as e:
            logger.error(f"Error predicting answer: {str(e)}")
            return "I don't know", 0.0
    
    def get_similar_question_answers(self, question: str, difficulty: str, category: str, limit: int = 5) -> List[Dict]:
        """Find similar questions from training data"""
        conn = self.get_db_connection()
        cursor = conn.cursor(dictionary=True)
        
        # Find similar questions based on keywords and context
        query = """
        SELECT question_text, ai_answer, score_achieved, max_possible_score, player_answer
        FROM bespoke_ai_training_data 
        WHERE model_id = %s 
        AND difficulty_id = %s 
        AND category_id = %s
        ORDER BY created_at DESC
        LIMIT %s
        """
        
        cursor.execute(query, (self.model_id, difficulty, category, limit))
        similar_questions = cursor.fetchall()
        
        cursor.close()
        conn.close()
        
        return similar_questions
    
    def generate_base_answer(self, question: str, similar_answers: List[Dict], player_answer: str = "") -> str:
        """Generate a base answer using learned patterns"""
        question_lower = question.lower()
        
        # Simple pattern matching based on question types
        if "when" in question_lower or "what year" in question_lower:
            # Look for date patterns in similar answers
            for similar in similar_answers:
                if similar['score_achieved'] > 0:
                    answer = similar['ai_answer']
                    # Extract potential years or dates
                    import re
                    dates = re.findall(r'\b(19|20)\d{2}\b', answer)
                    if dates:
                        return dates[0]
        
        elif "who" in question_lower:
            # Look for person names in successful answers
            for similar in similar_answers:
                if similar['score_achieved'] > 0:
                    answer = similar['ai_answer']
                    # Simple name pattern (words starting with capital letters)
                    import re
                    names = re.findall(r'\b[A-Z][a-z]+(?:\s+[A-Z][a-z]+)*\b', answer)
                    if names:
                        return names[0]
        
        elif "where" in question_lower:
            # Look for location patterns
            for similar in similar_answers:
                if similar['score_achieved'] > 0:
                    answer = similar['ai_answer']
                    # Return the answer that worked before for similar location questions
                    return answer
        
        elif "how many" in question_lower or "how much" in question_lower:
            # Look for numerical answers
            for similar in similar_answers:
                if similar['score_achieved'] > 0:
                    answer = similar['ai_answer']
                    import re
                    numbers = re.findall(r'\b\d+(?:\.\d+)?\b', answer)
                    if numbers:
                        return numbers[0]
        
        # If we have a player answer, learn from it
        if player_answer and len(similar_answers) > 0:
            # Find the most successful pattern that's similar to player answer
            best_answer = ""
            best_score = -1
            
            for similar in similar_answers:
                if similar['score_achieved'] > best_score:
                    best_score = similar['score_achieved']
                    best_answer = similar['ai_answer']
            
            if best_answer:
                return best_answer
        
        # Default fallback
        return "I need more data to answer this question accurately"
    
    def refine_answer(self, base_answer: str, similar_answers: List[Dict], predicted_efficiency: float) -> str:
        """Refine the answer based on model predictions and similar successful answers"""
        # If predicted efficiency is low, try to use the best performing similar answer
        if predicted_efficiency < 0.3 and similar_answers:
            best_answer = ""
            best_efficiency = 0
            
            for similar in similar_answers:
                efficiency = similar['score_achieved'] / max(similar['max_possible_score'], 1)
                if efficiency > best_efficiency:
                    best_efficiency = efficiency
                    best_answer = similar['ai_answer']
            
            if best_answer and best_efficiency > predicted_efficiency:
                return best_answer
        
        return base_answer
    
    def save_training_data(self, game_id: int, question_id: int, question_text: str, 
                          correct_answer: str, player_answer: str, ai_answer: str, 
                          score_achieved: int, max_possible_score: int, 
                          difficulty_id: int = None, category_id: int = None):
        """Save training data to database"""
        conn = self.get_db_connection()
        cursor = conn.cursor()
        
        query = """
        INSERT INTO bespoke_ai_training_data 
        (model_id, game_id, question_id, question_text, correct_answer, player_answer, 
         ai_answer, score_achieved, max_possible_score, difficulty_id, category_id, created_at, updated_at)
        VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, NOW(), NOW())
        """
        
        cursor.execute(query, (
            self.model_id, game_id, question_id, question_text, correct_answer, 
            player_answer, ai_answer, score_achieved, max_possible_score, 
            difficulty_id, category_id
        ))
        
        conn.commit()
        cursor.close()
        conn.close()
        
        logger.info(f"Training data saved for model {self.model_id}")
    
    def save_model(self):
        """Save the trained model to disk"""
        model_data = {
            'vectorizer': self.vectorizer,
            'score_predictor': self.score_predictor,
            'model_trained': self.model_trained
        }
        
        with open(self.model_path, 'wb') as f:
            pickle.dump(model_data, f)
        
        logger.info(f"Model {self.model_id} saved to {self.model_path}")
    
    def load_model(self):
        """Load the trained model from disk"""
        if os.path.exists(self.model_path):
            try:
                with open(self.model_path, 'rb') as f:
                    model_data = pickle.load(f)
                
                self.vectorizer = model_data['vectorizer']
                self.score_predictor = model_data['score_predictor']
                self.model_trained = model_data['model_trained']
                
                logger.info(f"Model {self.model_id} loaded from {self.model_path}")
            except Exception as e:
                logger.error(f"Error loading model {self.model_id}: {str(e)}")

def main():
    parser = argparse.ArgumentParser(description='Bespoke AI Model for Trivia Games')
    parser.add_argument('action', choices=['predict', 'train', 'save_data'], help='Action to perform')
    parser.add_argument('--model-id', type=int, required=True, help='AI model ID')
    parser.add_argument('--question', type=str, help='Question text')
    parser.add_argument('--player-answer', type=str, default='', help='Player answer')
    parser.add_argument('--difficulty', type=str, default='', help='Difficulty level')
    parser.add_argument('--category', type=str, default='', help='Category')
    parser.add_argument('--max-score', type=int, default=1, help='Maximum possible score')
    parser.add_argument('--game-id', type=int, help='Game ID')
    parser.add_argument('--question-id', type=int, help='Question ID')
    parser.add_argument('--correct-answer', type=str, help='Correct answer')
    parser.add_argument('--ai-answer', type=str, help='AI answer')
    parser.add_argument('--score-achieved', type=int, help='Score achieved')
    
    args = parser.parse_args()
    
    # Database configuration (from environment or config file)
    db_config = {
        'host': os.getenv('DB_HOST', '127.0.0.1'),
        'port': int(os.getenv('DB_PORT', 3306)),
        'database': os.getenv('DB_DATABASE', 'vue_template'),
        'user': os.getenv('DB_USERNAME', 'root'),
        'password': os.getenv('DB_PASSWORD', 'r00tadmin'),
        'autocommit': True
    }
    
    # Initialize the AI model
    ai_model = BespokeAIModel(db_config, args.model_id)
    
    if args.action == 'predict':
        if not args.question:
            print(json.dumps({'error': 'Question is required for prediction'}))
            sys.exit(1)
        
        answer, score = ai_model.predict_answer(
            args.question, 
            args.player_answer, 
            args.difficulty, 
            args.category, 
            args.max_score
        )
        
        result = {
            'answer': answer,
            'predicted_score': float(score),
            'model_id': args.model_id
        }
        
        print(json.dumps(result))
    
    elif args.action == 'train':
        success = ai_model.train_model()
        result = {
            'success': success,
            'model_id': args.model_id
        }
        print(json.dumps(result))
    
    elif args.action == 'save_data':
        required_args = ['game_id', 'question_id', 'question', 'correct_answer', 'ai_answer', 'score_achieved', 'max_score']
        missing_args = [arg for arg in required_args if not getattr(args, arg.replace('-', '_'), None)]
        
        if missing_args:
            print(json.dumps({'error': f'Missing required arguments: {missing_args}'}))
            sys.exit(1)
        
        ai_model.save_training_data(
            args.game_id,
            args.question_id, 
            args.question,
            args.correct_answer,
            args.player_answer,
            args.ai_answer,
            args.score_achieved,
            args.max_score,
            args.difficulty if args.difficulty else None,
            args.category if args.category else None
        )
        
        result = {'success': True, 'message': 'Training data saved'}
        print(json.dumps(result))

if __name__ == '__main__':
    main()