import requests
import time
import concurrent.futures
import csv
import statistics
import matplotlib.pyplot as plt

JUDGE0_URL = "http://localhost:2358"
BATCH_URL = f"{JUDGE0_URL}/submissions/batch?base64_encoded=false"
SYNC_URL = f"{JUDGE0_URL}/submissions?base64_encoded=false&wait=true"

# Sample simple python task: sum of two numbers
SOURCE_CODE = """
import sys
a, b = map(int, sys.stdin.read().split())
print(a + b)
"""

TEST_CASES = [
    {"stdin": "1 2", "expected_output": "3\n"},
    {"stdin": "10 20", "expected_output": "30\n"},
    {"stdin": "-5 5", "expected_output": "0\n"},
    {"stdin": "100 200", "expected_output": "300\n"},
    {"stdin": "0 0", "expected_output": "0\n"},
]

def submit_single_sync():
    """Submit a single task synchronously (wait=true) for baseline."""
    payload = {
        "source_code": SOURCE_CODE,
        "language_id": 71,
        "stdin": TEST_CASES[0]["stdin"],
        "expected_output": TEST_CASES[0]["expected_output"]
    }
    start_time = time.time()
    try:
        response = requests.post(SYNC_URL, json=payload, timeout=15)
        response.raise_for_status()
        duration = time.time() - start_time
        return duration, response.json().get("status", {}).get("id") == 3
    except Exception as e:
        print(f"Error: {e}")
        return time.time() - start_time, False

def submit_batch_async():
    """Submit a batch of 5 tests (typical student task) and poll until done."""
    submissions = []
    for tc in TEST_CASES:
        submissions.append({
            "source_code": SOURCE_CODE,
            "language_id": 71,
            "stdin": tc["stdin"],
            "expected_output": tc["expected_output"]
        })
    
    start_time = time.time()
    try:
        # POST batch
        res = requests.post(BATCH_URL, json={"submissions": submissions}, timeout=15)
        res.raise_for_status()
        tokens = [item["token"] for item in res.json()]
        
        # Poll
        tokens_str = ",".join(tokens)
        poll_url = f"{JUDGE0_URL}/submissions/batch?tokens={tokens_str}&base64_encoded=false&fields=status_id"
        
        while True:
            poll_res = requests.get(poll_url, timeout=15)
            poll_res.raise_for_status()
            data = poll_res.json()
            
            all_done = True
            for sub in data.get("submissions", []):
                if sub.get("status_id", 1) in (1, 2):  # In Queue or Processing
                    all_done = False
                    break
            
            if all_done:
                break
            time.sleep(0.5)
            if time.time() - start_time > 60:  # 1 min timeout for test
                return time.time() - start_time, False
                
        duration = time.time() - start_time
        return duration, True
    except Exception as e:
        print(f"Error: {e}")
        return time.time() - start_time, False

def run_load_test(concurrent_users):
    print(f"Running load test with {concurrent_users} concurrent users (5 test cases each)...")
    durations = []
    successes = 0
    
    start_time = time.time()
    
    with concurrent.futures.ThreadPoolExecutor(max_workers=concurrent_users) as executor:
        futures = [executor.submit(submit_batch_async) for _ in range(concurrent_users)]
        for future in concurrent.futures.as_completed(futures):
            duration, success = future.result()
            durations.append(duration)
            if success:
                successes += 1
                
    total_time = time.time() - start_time
    
    if durations:
        durations.sort()
        p50 = durations[int(len(durations) * 0.5)]
        p90 = durations[int(len(durations) * 0.9)]
        p99 = durations[int(len(durations) * 0.99)]
        metrics = {
            "users": concurrent_users,
            "success_rate": (successes / concurrent_users) * 100,
            "p50": p50,
            "p90": p90,
            "p99": p99,
            "avg": sum(durations) / len(durations),
            "throughput": concurrent_users / total_time
        }
    else:
        metrics = {"users": concurrent_users, "success_rate": 0, "p50": 0, "p90": 0, "p99": 0, "avg": 0, "throughput": 0}
        
    return metrics

def main():
    print(f"Checking if Judge0 is running at {JUDGE0_URL}...")
    try:
        res = requests.get(f"{JUDGE0_URL}/system_info", timeout=5)
        res.raise_for_status()
        print("Judge0 is reachable!")
    except Exception as e:
        print(f"Failed to connect to Judge0. Please make sure docker-compose is running. Error: {e}")
        return

    scenarios = [1, 5, 10, 20, 50]
    results = []
    
    for users in scenarios:
        metrics = run_load_test(users)
        results.append(metrics)
        print(f"Result for {users} users: Avg={metrics['avg']:.2f}s, Success={metrics['success_rate']}%")
        time.sleep(2)  # Cooldown
        
    # Write CSV
    with open('load_test_results.csv', 'w', newline='') as csvfile:
        fieldnames = ['users', 'success_rate', 'avg', 'p50', 'p90', 'p99', 'throughput']
        writer = csv.DictWriter(csvfile, fieldnames=fieldnames)
        writer.writeheader()
        for r in results:
            writer.writerow(r)
            
    print("Results saved to load_test_results.csv")
    
    # Plotting
    users = [r['users'] for r in results]
    avgs = [r['avg'] for r in results]
    p90s = [r['p90'] for r in results]
    
    plt.figure(figsize=(10, 6))
    plt.plot(users, avgs, marker='o', label='Average (s)')
    plt.plot(users, p90s, marker='s', label='P90 (s)')
    plt.title('Judge0 Average Response Time vs Concurrent Users')
    plt.xlabel('Concurrent Users (5 tests each)')
    plt.ylabel('Response Time (seconds)')
    plt.grid(True)
    plt.legend()
    plt.savefig('load_test_graph.png')
    print("Graph saved to load_test_graph.png")

if __name__ == "__main__":
    main()
