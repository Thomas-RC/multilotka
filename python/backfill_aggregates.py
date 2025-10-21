#!/usr/bin/env python3
"""
Backfill combo_aggregates in batches to avoid memory limits.
Processes combinations by first two digits (01-80) to stay within memory constraints.
"""

import sys
from clickhouse_driver import Client

def get_client():
    """Create ClickHouse client"""
    return Client(
        host='dbh',
        port=9000,
        user='multilotka',
        password='multilotka',
        database='analytics',
    )

def backfill_batch(client, prefix):
    """Aggregate combinations starting with specific prefix"""
    query = """
        INSERT INTO analytics.combo_aggregates
        SELECT
            combo,
            sumState(toUInt64(count)) AS total_hits,
            uniqExactState(bitShiftLeft(toUInt64(toRelativeDayNum(draw_date)), 32) + toUInt64(draw_number)) AS unique_draws,
            minState(draw_date) AS first_draw_date,
            maxState(draw_date) AS last_draw_date
        FROM analytics.draw_combinations
        WHERE startsWith(combo, %(prefix)s)
        GROUP BY combo
    """

    try:
        client.execute(query, {'prefix': prefix})
        print(f"✓ Batch {prefix} completed")
        return True
    except Exception as e:
        print(f"✗ Batch {prefix} failed: {e}")
        return False

def main():
    client = get_client()

    # Skip clearing - assume table is already empty or we're adding incrementally
    print("Starting backfill (table should be empty or will merge with existing data)...")

    # Process by first two digits
    prefixes = [f"{i:02d}" for i in range(1, 81)]

    total = len(prefixes)
    success = 0

    print(f"\nProcessing {total} batches...")
    for idx, prefix in enumerate(prefixes, 1):
        print(f"[{idx}/{total}] Processing combinations starting with {prefix}-...")
        if backfill_batch(client, prefix):
            success += 1

    print(f"\n{'='*60}")
    print(f"Completed: {success}/{total} batches")

    # Verify results
    result = client.execute("SELECT count() FROM analytics.combo_aggregates")
    print(f"Total unique combinations: {result[0][0]:,}")

    if success == total:
        print("✓ Backfill successful!")
        return 0
    else:
        print(f"✗ {total - success} batches failed")
        return 1

if __name__ == '__main__':
    sys.exit(main())
