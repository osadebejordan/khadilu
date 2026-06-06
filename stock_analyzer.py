import mysql.connector
from datetime import datetime, timedelta
import csv

def run_analysis():
    try:
        # Connect to the Khadilu Hub database
        db = mysql.connector.connect(
            host="localhost",
            user="root",
            password="",
            database="khadilu_hub"
        )
        cursor = db.cursor(dictionary=True)

        # 1. Identify "Dead Stock" (Items not sold in the last 30 days)
        thirty_days_ago = (datetime.now() - timedelta(days=30)).strftime('%Y-%m-%d %H:%M:%S')
        
        query = """
            SELECT p.id, p.name, p.brand, p.current_stock, p.cost_price, 
                   (p.current_stock * p.cost_price) as frozen_capital
            FROM products p
            WHERE p.is_deleted = 0 
            AND p.current_stock > 0
            AND p.id NOT IN (
                SELECT product_id FROM stock_outflow 
                WHERE created_at > %s
            )
            ORDER BY frozen_capital DESC
        """
        
        cursor.execute(query, (thirty_days_ago,))
        dead_stock = cursor.fetchall()

        if not dead_stock:
            print("Inventory is healthy! No dead stock detected.")
            return

        # 2. Export to CSV for business review
        filename = f"dead_stock_report_{datetime.now().strftime('%Y%m%d')}.csv"
        with open(filename, mode='w', newline='') as file:
            writer = csv.DictWriter(file, fieldnames=['id', 'name', 'brand', 'current_stock', 'cost_price', 'frozen_capital'])
            writer.writeheader()
            writer.writerows(dead_stock)

        total_frozen = sum(item['frozen_capital'] for item in dead_stock)
        print(f"Analysis Complete. Found {len(dead_stock)} stagnant items.")
        print(f"Total Frozen Capital: ₦{total_frozen:,.2f}")
        print(f"Detailed report saved as: {filename}")

    except mysql.connector.Error as err:
        print(f"Database Error: {err}")
    finally:
        if 'db' in locals() and db.is_connected():
            db.close()

if __name__ == "__main__":
    run_analysis()